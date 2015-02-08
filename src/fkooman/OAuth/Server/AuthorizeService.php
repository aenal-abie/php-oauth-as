<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\OAuth\Server;

use Twig_Loader_Filesystem;
use Twig_Environment;
use fkooman\Rest\Service;
use fkooman\Http\Request;
use fkooman\Http\Exception\BadRequestException;
use fkooman\OAuth\Common\Scope;
use fkooman\Http\RedirectResponse;
use fkooman\Rest\Plugin\UserInfo;

class AuthorizeService extends Service
{
    /** @var fkooman\OAuth\Server\PdoStorage */
    private $storage;

    /** @var int */
    private $accessTokenExpiry;

    /** @var bool */
    private $allowRegExpRedirectUriMatch;

    public function __construct(PdoStorage $storage, $accessTokenExpiry = 3600, $allowRegExpRedirectUriMatch = false)
    {
        parent::__construct();

        $this->storage = $storage;
        $this->accessTokenExpiry = $accessTokenExpiry;
        $this->allowRegExpRedirectUriMatch = (bool) $allowRegExpRedirectUriMatch;

        $compatThis = &$this;

        $this->get(
            '/',
            function (Request $request, UserInfo $userInfo) use ($compatThis) {
                return $compatThis->getAuthorization($request, $userInfo);
            }
        );

        $this->post(
            '/',
            function (Request $request, UserInfo $userInfo) use ($compatThis) {
                return $compatThis->postAuthorization($request, $userInfo);
            }
        );
    }

    public function getAuthorization(Request $request, UserInfo $userInfo)
    {
        // FIXME: validate all these parameters
        $clientId     = $request->getQueryParameter('client_id');
        $responseType = $request->getQueryParameter('response_type');
        $redirectUri  = $request->getQueryParameter('redirect_uri');
        // FIXME: scope can never be empty, if the client requests no scope we should have a default scope!
        $scope        = Scope::fromString($request->getQueryParameter('scope'));
        $state        = $request->getQueryParameter('state');

        if (null === $clientId) {
            throw new BadRequestException('client_id missing');
        }
        if (null === $responseType) {
            throw new BadRequestException('response_type missing');
        }
        $client = $this->storage->getClient($clientId);
        if (false === $client) {
            throw new BadRequestException('client not registered');
        }
        if (null === $redirectUri) {
            $redirectUri = $client->getRedirectUri();
        } else {
            if (!$client->verifyRedirectUri($redirectUri, $this->allowRegExpRedirectUriMatch)) {
                throw new BadRequestException(
                    'specified redirect_uri not the same as registered redirect_uri'
                );
            }
        }

        if ($responseType !== $client->getType()) {
            return new ClientResponse(
                $client,
                $request,
                $redirectUri,
                array(
                    'error' => 'unsupported_response_type',
                    'error_description' => 'response_type not supported by client profile'
                )
            );
        }

        if (!$scope->isSubsetOf(Scope::fromString($client->getAllowedScope()))) {
            return new ClientResponse(
                $client,
                $request,
                $redirectUri,
                array(
                    'error' => 'invalid_scope',
                    'error_description' => 'not authorized to request this scope'
                )
            );
        }
        
        if ($client->getDisableUserConsent()) {
            // we do not require approval by the user
            $approvedScope = array('scope' => $scope->toString());
        } else {
            $approvedScope = $this->storage->getApprovalByResourceOwnerId($clientId, $userInfo->getUserId());
        }

        if (false === $approvedScope || false === $scope->isSubsetOf(Scope::fromString($approvedScope['scope']))) {
            // we need to ask for approval
            $twig = $this->getTwig();
            return $twig->render(
                "askAuthorization.twig",
                array(
                    'resourceOwnerId' => $userInfo->getUserId(),
                    'sslEnabled' => "https" === $request->getRequestUri()->getScheme(),
                    'contactEmail' => $client->getContactEmail(),
                    'scopes' => $scope->toArray(),
                    'clientName' => $client->getName(),
                    'clientId' => $client->getId(),
                    'clientDescription' => $client->getDescription()
                )
            );
        } else {
            // we already have approval
            if ("token" === $responseType) {
                // implicit grant
                // FIXME: return existing access token if it exists for this exact client, resource owner and scope?
                $accessToken = bin2hex(openssl_random_pseudo_bytes(16));
                $this->storage->storeAccessToken(
                    $accessToken,
                    time(),
                    $clientId,
                    $userInfo->getUserId(),
                    $scope->toString(),
                    $this->accessTokenExpiry
                );
                return new ClientResponse(
                    $client,
                    $request,
                    $redirectUri,
                    array(
                        "access_token" => $accessToken,
                        "expires_in" => $this->accessTokenExpiry,
                        "token_type" => "bearer",
                        "scope" => $scope->toString()
                    )
                );
            } else {
                // authorization code grant
                $authorizationCode = bin2hex(openssl_random_pseudo_bytes(16));
                $this->storage->storeAuthorizationCode(
                    $authorizationCode,
                    $userInfo->getUserId(),
                    time(),
                    $clientId,
                    $redirectUri,
                    $scope->getScope()
                );
                return new ClientResponse(
                    $client,
                    $request,
                    $redirectUri,
                    array(
                        'code' => $authorizationCode
                    )
                );
            }
        }
    }

    public function postAuthorization(Request $request, UserInfo $userInfo)
    {
        $clientId     = $request->getQueryParameter('client_id');
        $responseType = $request->getQueryParameter('response_type');
        $redirectUri  = $request->getQueryParameter('redirect_uri');
        $scope        = Scope::fromString($request->getQueryParameter('scope'));
        $state        = $request->getQueryParameter('state');
        // FIXME: validate all parameters...

        // FIXME: csrf! referer check!
        if ($request->getHeader('HTTP_REFERER') !== $request->getRequestUri()->getUri()) {
            throw new BadRequestException('CSRF protection triggered');
        }

        $approval = $request->getPostParameter('approval');

        // FIXME: client may be false if it is a fake post!
        $client = $this->storage->getClient($clientId);

        if ("approve" !== $approval) {
            return new ClientResponse(
                $client,
                $request,
                $redirectUri,
                array(
                    'error' => 'access_denied',
                    'error_description' => 'not authorized by resource owner'
                )
            );
        }

        $approvedScope = $this->storage->getApprovalByResourceOwnerId($clientId, $userInfo->getUserId());
        if (false === $approvedScope) {
            // no approved scope stored yet, new entry
            $refreshToken = ("code" === $responseType) ? bin2hex(openssl_random_pseudo_bytes(16)) : null;
            $this->storage->addApproval($clientId, $userInfo->getUserId(), $scope->toString(), $refreshToken);
        } else {
            // FIXME: update merges the scopes?
            $this->storage->updateApproval($clientId, $userInfo->getUserId(), $scope->getScope());
        }

        // redirect back to the authorize uri, this time there should be an
        // approval...
        // FIXME: maybe move the already having approval code from getAuthorize
        // in a separate function as to avoid this extra 'redirect'
        return new RedirectResponse($request->getRequestUri()->getUri(), 302);
    }

    private function getTwig()
    {
        $configTemplateDir = dirname(dirname(dirname(dirname(__DIR__)))).'/config/views';
        $defaultTemplateDir = dirname(dirname(dirname(dirname(__DIR__)))).'/views';
        $templateDirs = array();
        if (false !== is_dir($configTemplateDir)) {
            $templateDirs[] = $configTemplateDir;
        }
        $templateDirs[] = $defaultTemplateDir;
        return new Twig_Environment(
            new Twig_Loader_Filesystem($templateDirs)
        );
    }
}
