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

require_once 'OAuthHelper.php';

use fkooman\Json\Json;

use fkooman\Http\Request as HttpRequest;

class ApiTest extends OAuthHelper
{
    protected $_api;

    public function setUp()
    {
        parent::setUp();
        $this->_api = new Api($this->config, NULL);

        $oauthStorageBackend = 'fkooman\\OAuth\\Server\\' . $this->config->getValue('storageBackend');
        $storage = new $oauthStorageBackend($this->config);

        $resourceOwner = array(
            "id" => "fkooman",
            "entitlement" => array(),
            "ext" => array()
        );
        $storage->updateResourceOwner(new MockResourceOwner($resourceOwner));

        $storage->addApproval('testclient', 'fkooman', 'read', NULL);
        $storage->storeAccessToken('12345abc', time(), 'testcodeclient', 'fkooman', 'authorizations', 3600);
    }

    public function testRetrieveAuthorizations()
    {
        $h = new HttpRequest("http://www.example.org/api.php");
        $h->setPathInfo("/authorizations/");
        $h->setHeader("Authorization", "Bearer 12345abc");
        $response = $this->_api->handleRequest($h);
        $this->assertEquals(json_decode('[{"scope":"read","id":"testclient","name":"Simple Test Client","description":"Client for unit testing","redirect_uri":"http:\/\/localhost\/php-oauth\/unit\/test.html","type":"user_agent_based_application","icon":null,"allowed_scope":"read"}]', true), $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("application/json", $response->getHeader("Content-Type"));
    }

    public function testAddAuthorizations()
    {
        $h = new HttpRequest("http://www.example.org/api.php");
        $h->setRequestMethod("POST");
        $h->setPathInfo("/authorizations/");
        $h->setHeader("Authorization", "Bearer 12345abc");
        $h->setContent(Json::encode(array("client_id" => "testcodeclient", "scope" => "read", "refresh_token" => NULL)));
        $response = $this->_api->handleRequest($h);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testAddAuthorizationsUnregisteredClient()
    {
        $h = new HttpRequest("http://www.example.org/api.php");
        $h->setRequestMethod("POST");
        $h->setPathInfo("/authorizations/");
        $h->setHeader("Authorization", "Bearer 12345abc");
        $h->setContent(Json::encode(array("client_id" => "nonexistingclient", "scope" => "read")));
        $response = $this->_api->handleRequest($h);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(json_decode('{"error":"invalid_request","error_description":"client is not registered"}', true), $response->getContent());
    }

    public function testAddAuthorizationsUnsupportedScope()
    {
        $h = new HttpRequest("http://www.example.org/api.php");
        $h->setRequestMethod("POST");
        $h->setPathInfo("/authorizations/");
        $h->setHeader("Authorization", "Bearer 12345abc");
        $h->setContent(Json::encode(array("client_id" => "testcodeclient", "scope" => "UNSUPPORTED SCOPE")));
        $response = $this->_api->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(json_decode('{"error":"invalid_request","error_description":"invalid scope for this client"}', true), $response->getContent());
    }

    public function testGetAuthorization()
    {
        $h = new HttpRequest("http://www.example.org/api.php");
        $h->setPathInfo("/authorizations/testclient");
        $h->setHeader("Authorization", "Bearer 12345abc");
        // FIXME: test with non existing client_id!
        $response = $this->_api->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(json_decode('{"client_id":"testclient","resource_owner_id":"fkooman","scope":"read","refresh_token":null}', true), $response->getContent());
    }

    public function testDeleteAuthorization()
    {
        $h = new HttpRequest("http://www.example.org/api.php");
        $h->setRequestMethod("DELETE");
        $h->setPathInfo("/authorizations/testclient");
        $h->setHeader("Authorization", "Bearer 12345abc");
        // FIXME: test with non existing client_id!
        $response = $this->_api->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(json_decode('{"ok":true}', true), $response->getContent());
    }

}
