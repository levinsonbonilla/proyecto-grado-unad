<?php

namespace App\Tests\Integration\Regression;

use App\Tests\Integration\Support\StoreScenarioTestCase;

class H05StorefrontTemplateTextTest extends StoreScenarioTestCase
{
    public function testStorefrontFooterDoesNotShowTemplateEmail(): void
    {
        foreach (['es' => 'Tu correo electrónico', 'en' => 'Your email address'] as $locale => $placeholder) {
            $this->client->request('GET', self::TENANT_A_HOST . '/' . $locale);
            $this->assertResponseIsSuccessful();
            $html = (string) $this->client->getResponse()->getContent();
            $this->assertStringNotContainsString('email@example.com', $html);
            $this->assertStringContainsString($placeholder, $html);
        }
    }
}
