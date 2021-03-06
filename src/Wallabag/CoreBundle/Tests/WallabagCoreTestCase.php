<?php

namespace Wallabag\CoreBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class WallabagCoreTestCase extends WebTestCase
{
    private $client = null;

    public function getClient()
    {
        return $this->client;
    }

    public function setUp()
    {
        $this->client = static::createClient();
    }

    public function logInAs($username)
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('button[type=submit]')->form();
        $data = array(
            '_username' => $username,
            '_password' => 'mypassword',
        );

        $this->client->submit($form, $data);
    }
}
