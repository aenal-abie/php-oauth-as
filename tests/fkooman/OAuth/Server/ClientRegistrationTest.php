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

use fkooman\OAuth\Server\Exception\ClientRegistrationException;

class ClientRegistrationTest extends \PHPUnit_Framework_TestCase
{
    public static function validProvider()
    {
        return array(
            array("foo", null, "user_agent_based_application", "http://www.example.org/cb", "Foo Client"),
            array("foo", "s3cr3t", "web_application", "http://www.example.org/cb", "Foo Client"),
            array("foo:bar", null, "user_agent_based_application", "http://www.example.org/cb", "Foo Client"),
            array("foo", "s3cr3t", "native_application", "twitter://app/callback", "Foo Client"),
            array(null, null, "web_application", "http://www.example.org/cb", "Foo Client"),
            array(null, "s3cr3t", "web_application", "http://www.example.org/cb", "Foo Client"),
        );
    }

    public static function validProviderFromArray()
    {
        return array(
            array('foo', "bar", "web_application", "http://xyz", "Foo", "foo", "http://x/a.png", "Description", "f@example.org"),
        );
    }

    public static function invalidProvider()
    {
        return array(
            array('√', null, null, null, null, "id contains invalid character"),
            array('foo', null, "xyz", null, null, "type not supported"),
            array('foo', "√", null, null, null, "secret contains invalid character"),
            array('foo', "bar", "web_application", "http://x/y", null, "name cannot be empty"),
            array('foo', "bar", "web_application", "http://", null, "redirect_uri should be valid URL"),
            array('foo', "bar", "web_application", "http://foo/bar#fragment", null, "redirect_uri cannot contain a fragment"),
        );
    }

    public static function invalidProviderFromArray()
    {
        return array(
            array('foo', "bar", "web_application", "http://xyz", "Foo", "√", null, null, null, "scope is invalid"),
            array('foo', "bar", "web_application", "http://xyz", "Foo", "foo", "x", null, null, "icon should be either empty or valid URL with path"),
            array('foo', "bar", "web_application", "http://xyz", "Foo", "foo", "http://x/a.png", "Description", "nomail", "contact email should be either empty or valid email address"),
        );
    }

   /**
     * @dataProvider validProvider
     */
    public function testValid($id, $secret, $type, $redirectUri, $name)
    {
        $c = new ClientRegistration($id, $secret, $type, $redirectUri, $name);
        if (null === $id) {
            // generated, match regexp
            $this->assertRegexp("|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|", $c->getId());
        } else {
            $this->assertEquals($id, $c->getId());
        }
        if (null === $secret && "web_application" === $c->getType()) {
            $this->assertRegexp("|[0-9a-f]+|", $c->getSecret());
        } else {
            $this->assertEquals($secret, $c->getSecret());
        }
        $this->assertEquals($type, $c->getType());
        $this->assertEquals($redirectUri, $c->getRedirectUri());
        $this->assertEquals($name, $c->getName());
        $this->assertNull($c->getDescription());
        $this->assertNull($c->getIcon());
        $this->assertNull($c->getAllowedScope());
        $this->assertNull($c->getContactEmail());
    }

   /**
     * @dataProvider invalidProvider
     */
    public function testInvalid($id, $secret, $type, $redirectUri, $name, $exceptionMessage)
    {
        try {
            $c = new ClientRegistration($id, $secret, $type, $redirectUri, $name);
            $this->assertTrue(false);
        } catch (ClientRegistrationException $e) {
            $this->assertEquals($exceptionMessage, $e->getMessage());
        }
    }

   /**
     * @dataProvider validProviderFromArray
     */
    public function testValidFromArray($id, $secret, $type, $redirectUri, $name, $allowedScope, $icon, $description, $contactEmail)
    {
        $c = ClientRegistration::fromArray(array("id" => $id, "secret" => $secret, "type" => $type, "redirect_uri" => $redirectUri, "name" => $name, "allowed_scope" => $allowedScope, "icon" => $icon, "description" => $description, "contact_email" => $contactEmail));
        $this->assertEquals($id, $c->getId());
        $this->assertEquals($secret, $c->getSecret());
        $this->assertEquals($type, $c->getType());
        $this->assertEquals($redirectUri, $c->getRedirectUri());
        $this->assertEquals($name, $c->getName());
        $this->assertEquals($allowedScope, $c->getAllowedScope());
        $this->assertEquals($icon, $c->getIcon());
        $this->assertEquals($description, $c->getDescription());
        $this->assertTrue($c->getUserConsent());
        $this->assertEquals($contactEmail, $c->getContactEmail());
        $this->assertEquals(array("id" => $id, "secret" => $secret, "type" => $type, "redirect_uri" => $redirectUri, "user_consent" => true, "name" => $name, "allowed_scope" => $allowedScope, "icon" => $icon, "description" => $description, "contact_email" => $contactEmail), $c->getClientAsArray());
    }

   /**
     * @dataProvider invalidProviderFromArray
     */
    public function testInvalidFromArray($id, $secret, $type, $redirectUri, $name, $allowedScope, $icon, $description, $contactEmail, $exceptionMessage)
    {
        try {
            $c = ClientRegistration::fromArray(array("id" => $id, "secret" => $secret, "type" => $type, "redirect_uri" => $redirectUri, "name" => $name, "allowed_scope" => $allowedScope, "icon" => $icon, "description" => $description, "contact_email" => $contactEmail));
            $this->assertTrue(false);
        } catch (ClientRegistrationException $e) {
            $this->assertEquals($exceptionMessage, $e->getMessage());
        }
    }

    public function testBrokenFromArray()
    {
        try {
            $c = ClientRegistration::fromArray(array("foo" => "bar"));
            $this->assertTrue(false);
        } catch (ClientRegistrationException $e) {
            $this->assertEquals("not a valid client, 'id' not set", $e->getMessage());
        }
    }
}
