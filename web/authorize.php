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

require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Ini\IniReader;
use fkooman\OAuth\Server\PdoStorage;
use fkooman\OAuth\Server\AuthorizeService;
use fkooman\OAuth\Server\Authenticator;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\Http\Exception\HttpException;

set_error_handler(
    function ($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
);

try {
    $iniReader = IniReader::fromFile(
        dirname(__DIR__).'/config/oauth.ini'
    );

    $db = new PDO(
        $iniReader->v('PdoStorage', 'dsn'),
        $iniReader->v('PdoStorage', 'username', false),
        $iniReader->v('PdoStorage', 'password', false)
    );

    $auth = new Authenticator($iniReader);
    $authenticationPlugin = $auth->getAuthenticationPlugin();

    $authorizeService = new AuthorizeService(
        new PdoStorage($db),
        null,
        $iniReader->v('accessTokenExpiry'),
        $iniReader->v('allowRegExpRedirectUriMatch')
    );
    $authorizeService->registerOnMatchPlugin($authenticationPlugin);

    $authorizeService->run()->sendResponse();
} catch (Exception $e) {
    AuthorizeService::handleException($e)->sendResponse();
}
