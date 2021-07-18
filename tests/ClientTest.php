<?php

/*
 * This file is part of the Panther project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Panther\Tests;

use Facebook\WebDriver\Exception\InvalidSelectorException;
use Facebook\WebDriver\JavaScriptExecutor;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar as BrowserKitCookieJar;
use Symfony\Component\DomCrawler\Crawler as DomCrawlerCrawler;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\Cookie\CookieJar;
use Symfony\Component\Panther\DomCrawler\Crawler;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\Panther\ProcessManager\ChromeManager;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClientTest extends TestCase
{
    public function testCreatePantherClientInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::createPantherClient([
            'browser_arguments' => 'bad browser arguments data type',
        ]);
    }

    public function testCreatePantherClientWithBrowserArgument(): void
    {
        $client = self::createPantherClient([
            'browser' => PantherTestCase::CHROME,
            'browser_arguments' => ['--window-size=1400,900'],
        ]);
        $this->assertInstanceOf(AbstractBrowser::class, $client);
        $this->assertInstanceOf(WebDriver::class, $client);
        $this->assertInstanceOf(JavaScriptExecutor::class, $client);
        $this->assertInstanceOf(KernelInterface::class, self::$kernel);

        // I'm not sure exactly how to start with a clear class, since the
        // test file assumes the client will be present for the next tests
        // So I'm cleaning up this way here so that we can have a default
        // client next
        self::$pantherClient = null;
    }

    public function testCreateClient(): void
    {
        $client = self::createPantherClient();
        $this->assertInstanceOf(AbstractBrowser::class, $client);
        $this->assertInstanceOf(WebDriver::class, $client);
        $this->assertInstanceOf(JavaScriptExecutor::class, $client);
        $this->assertInstanceOf(KernelInterface::class, self::$kernel);
    }

    public function testWaitForEmptyLocator(): void
    {
        $this->expectException(InvalidSelectorException::class);

        $client = self::createPantherClient();
        $client->request('GET', '/waitfor.html');
        $client->waitFor('');
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitFor(string $locator): void
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor.html');
        $c = $client->waitFor($locator);
        $this->assertInstanceOf(Crawler::class, $c);
        $this->assertSame('Hello', $crawler->filter('#hello')->text(null, true));
    }

    public function testWaitForHiddenInputElement(): void
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor-hidden-input.html');
        $c = $client->waitFor('#hello');
        $this->assertInstanceOf(Crawler::class, $c);
        $this->assertSame('Hello', $crawler->filter('#hello')->getAttribute('value'));
    }

    public function waitForDataProvider(): iterable
    {
        yield 'css selector' => ['locator' => '#hello'];
        yield 'xpath expression' => ['locator' => '//*[@id="hello"]'];
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForVisibility(string $locator): void
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor-element-to-be-visible.html');
        $c = $client->waitForVisibility($locator);
        $this->assertInstanceOf(Crawler::class, $c);
        $this->assertSame('Hello', $crawler->filter('#hello')->text(null, true));
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForInvisibility(string $locator): void
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor-element-to-be-invisible.html');
        $c = $client->waitForInvisibility($locator);
        $this->assertInstanceOf(Crawler::class, $c);
        $this->assertSame('', $crawler->filter('#hello')->text(null, true));
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForElementToContain(string $locator): void
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor-element-to-contain.html');
        $c = $client->waitForElementToContain($locator, 'new content');
        $this->assertInstanceOf(Crawler::class, $c);
        $this->assertSame('Hello new content', $crawler->filter('#hello')->text(null, true));
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForElementToNotContain(string $locator): void
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor-element-to-not-contain.html');
        $c = $client->waitForElementToNotContain($locator, 'removed content');
        $this->assertInstanceOf(Crawler::class, $c);
        $this->assertSame('Hello', $crawler->filter('#hello')->text(null, true));
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForEnabled(string $locator): void
    {
        $client = self::createPantherClient();
        $client->request('GET', '/waitfor-input-to-be-enabled.html');
        $crawler = $client->waitForEnabled($locator);
        $this->assertInstanceOf(Crawler::class, $crawler);
        $this->assertTrue($crawler->filter('#hello')->isEnabled());
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForDisabled(string $locator): void
    {
        $client = self::createPantherClient();
        $client->request('GET', '/waitfor-input-to-be-disabled.html');
        $crawler = $client->waitForDisabled($locator);
        $this->assertInstanceOf(Crawler::class, $crawler);
        $this->assertFalse($crawler->filter('#hello')->isEnabled());
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForAttributeToContain(string $locator): void
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor-attribute-to-contain.html');
        $c = $client->waitForAttributeToContain($locator, 'data-old-price', '42');
        $this->assertInstanceOf(Crawler::class, $c);
        $this->assertSame('42', $crawler->filter('#hello')->getAttribute('data-old-price'));
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForAttributeToNotContain(string $locator): void
    {
        $client = self::createPantherClient();
        $client->request('GET', '/waitfor-attribute-to-contain.html');
        $crawler = $client->waitForAttributeToContain($locator, 'data-old-price', '36');
        $this->assertInstanceOf(Crawler::class, $crawler);
        $this->assertSame('36', $crawler->filter('#hello')->getAttribute('data-old-price'));
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForStalenessElement(string $locator): void
    {
        $client = self::createPantherClient();
        $client->request('GET', '/waitfor-staleness.html');
        $crawler = $client->waitForStaleness($locator);
        $this->assertInstanceOf(Crawler::class, $crawler);
    }

    public function testExecuteScript(): void
    {
        $client = self::createPantherClient();
        $client->request('GET', '/basic.html');
        $innerText = $client->executeScript('return document.querySelector(arguments[0]).innerText;', ['.p-1']);
        $this->assertSame('P1', $innerText);
    }

    public function testExecuteScriptLogicExceptionWhenDriverIsNotStartedYet(): void
    {
        $this->expectException(\LogicException::class);
        $client = Client::createChromeClient();
        $client->executeScript('return document.querySelector(arguments[0]).innerText;', ['.p-1']);
    }

    public function testExecuteAsyncScript(): void
    {
        $client = self::createPantherClient();
        $client->request('GET', '/basic.html');
        $innerText = $client->executeAsyncScript(<<<JS
setTimeout(function (parentArgs) {
    const callback = parentArgs[parentArgs.length - 1];
    const t = document.querySelector(parentArgs[0]).innerText;
    callback(t);
}, 100, arguments);
JS
            , ['.p-1']);

        $this->assertSame('P1', $innerText);
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testGetCrawler(callable $clientFactory, string $type): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        $this->assertInstanceOf(DomCrawlerCrawler::class, $crawler);
        if (Client::class === $type) {
            $this->assertInstanceOf(Crawler::class, $crawler);
        }
    }

    public function testRefreshCrawler(): void
    {
        $client = self::createPantherClient();

        $crawler = $client->request('GET', '/js-redirect.html');
        $linkCrawler = $crawler->selectLink('Redirect Link');

        $this->assertSame('Redirect Link', $linkCrawler->text(null, true));

        $client->click($linkCrawler->link());
        $client->wait(5)->until(WebDriverExpectedCondition::titleIs('A basic page'));

        $refreshedCrawler = $client->refreshCrawler();

        $this->assertInstanceOf(Crawler::class, $refreshedCrawler);
        $this->assertSame(self::$baseUri.'/basic.html', $refreshedCrawler->getUri());
        $this->assertSame('Hello', $refreshedCrawler->filter('h1')->text(null, true));
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testFollowLink(callable $clientFactory, string $type): void
    {
        /** @var AbstractBrowser $client */
        $client = $clientFactory();
        $crawler = $client->request('GET', static::$baseUri.'/link.html');
        $link = $crawler->filter('#d2')->selectLink('E1')->link();

        $crawler = $client->click($link);
        $this->assertInstanceOf(DomCrawlerCrawler::class, $crawler);
        if (Client::class === $type) {
            $this->assertInstanceOf(Crawler::class, $crawler);
        }
        $this->assertSame(self::$baseUri.'/basic.html#e12', $crawler->getUri());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testSubmitForm(callable $clientFactory): void
    {
        /** @var AbstractBrowser $client */
        $client = $clientFactory();
        $crawler = $client->request('GET', static::$baseUri.'/form.html');
        $form = $crawler->filter('form')->eq(0)->selectButton('OK')->form([
            'i1' => 'Reclus',
        ]);

        $crawler = $client->submit($form);
        $this->assertInstanceOf(DomCrawlerCrawler::class, $crawler);
        if ($client instanceof Client) {
            $this->assertInstanceOf(Crawler::class, $crawler);
        }
        $this->assertSame(self::$baseUri.'/form-handle.php', $crawler->getUri());
        $this->assertSame('I1: Reclus', $crawler->filter('#result')->text(null, true));

        $crawler = $client->back();
        $form = $crawler->filter('form')->eq(0)->form([
            'i1' => 'Michel',
        ]);

        $crawler = $client->submit($form);
        $this->assertSame('I1: n/a', $crawler->filter('#result')->text(null, true));
        $this->assertSame(self::$baseUri.'/form-handle.php?i1=Michel&i2=&i3=&i4=i4a', $crawler->getUri());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testSubmitFormWithValues(callable $clientFactory, string $type): void
    {
        /** @var AbstractBrowser $client */
        $client = $clientFactory();
        $crawler = $client->request('GET', static::$baseUri.'/form.html');
        $form = $crawler->filter('form')->eq(0)->selectButton('OK')->form();

        $crawler = $client->submit($form, [
            'i1' => 'Reclus',
        ]);
        $this->assertInstanceOf(DomCrawlerCrawler::class, $crawler);
        if (Client::class === $type) {
            $this->assertInstanceOf(Crawler::class, $crawler);
        }
        $this->assertSame(self::$baseUri.'/form-handle.php', $crawler->getUri());
        $this->assertSame('I1: Reclus', $crawler->filter('#result')->text(null, true));
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testHistory(callable $clientFactory): void
    {
        /** @var AbstractBrowser $client */
        $client = $clientFactory();
        $crawler = $client->request('GET', self::$baseUri.'/link.html');
        $this->assertSame(self::$baseUri.'/link.html', $crawler->getUri());

        $crawler = $client->click($crawler->selectLink('E2')->link());
        $this->assertSame(self::$baseUri.'/basic.html#e2', $crawler->getUri());

        $crawler = $client->back();
        $this->assertSame(self::$baseUri.'/link.html', $crawler->getUri());

        $crawler = $client->forward();
        $this->assertSame(self::$baseUri.'/basic.html#e2', $crawler->getUri());

        $crawler = $client->reload();
        $this->assertSame(self::$baseUri.'/basic.html#e2', $crawler->getUri());

        $client->restart();
        $crawler = $client->request('GET', self::$baseUri.'/link.html');
        $this->assertSame(self::$baseUri.'/link.html', $crawler->getUri());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testCookie(callable $clientFactory, string $type): void
    {
        /** @var AbstractBrowser $client */
        $client = $clientFactory();
        $cookieJar = $client->getCookieJar();
        $cookieJar->clear(); // Firefox keeps the existing context by default, be sure to clear existing cookies

        $crawler = $client->request('GET', self::$baseUri.'/cookie.php');
        $this->assertSame('0', $crawler->filter('#barcelona')->text(null, true));

        $this->assertInstanceOf(BrowserKitCookieJar::class, $cookieJar);
        if (Client::class === $type) {
            $this->assertInstanceOf(CookieJar::class, $cookieJar);
        }

        $this->assertCount(1, $client->getCookieJar()->all());
        $cookie = $cookieJar->get('barcelona', '/cookie.php', '127.0.0.1');
        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertSame('barcelona', $cookie->getName());
        $this->assertSame('1', $cookie->getValue());
        $this->assertNull($cookie->getExpiresTime());
        $this->assertSame('/cookie.php', $cookie->getPath());
        $this->assertSame('127.0.0.1', $cookie->getDomain());
        $this->assertFalse($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());

        $this->assertNull($cookieJar->get('barcelona', '/cookies.php', '127.0.0.1'));
        $this->assertNull($cookieJar->get('barcelona', '/cookie.php', 'example.com'));
        $this->assertNull($cookieJar->get('barcelona', '/', '127.0.0.1'));
        $this->assertNotNull($cookieJar->get('barcelona', '/cookie.php'));
        $this->assertNotNull($cookieJar->get('barcelona', '/cookie.php/bar', '127.0.0.1'));

        $crawler = $client->reload();
        $this->assertSame('1', $crawler->filter('#barcelona')->text(null, true));

        $this->assertNotEmpty($cookieJar->all());
        $cookieJar->clear();
        $this->assertEmpty($cookieJar->all());

        $cookieJar->set(new Cookie('foo', 'bar'));
        $crawler = $client->reload();
        $this->assertSame('bar', $cookieJar->get('foo')->getValue());
        $this->assertSame('0', $crawler->filter('#barcelona')->text(null, true));
        $this->assertSame('bar', $crawler->filter('#foo')->text(null, true));

        $cookieJar->expire('foo');
        $this->assertNull($cookieJar->get('foo'));
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testServerPort(callable $clientFactory): void
    {
        $expectedPort = $_SERVER['PANTHER_WEB_SERVER_PORT'] ?? '9080';
        $clientFactory();
        $this->assertEquals($expectedPort, mb_substr(self::$baseUri, -4));
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testBrowserProvider(callable $clientFactory): void
    {
        $client = $clientFactory();
        if (!$client instanceof Client) {
            $this->markTestSkipped();
        }

        $client->request('GET', self::$baseUri.'/ua.php');
        $this->assertStringContainsString($client->getBrowserManager() instanceof ChromeManager ? 'Chrome' : 'Firefox', $client->getPageSource());
    }

    public function testGetHistory(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('History is not available when using WebDriver.');

        self::createPantherClient()->getHistory();
    }

    public function testPing(): void
    {
        $client = self::createPantherClient();
        $client->request('GET', '/basic.html');

        $this->assertTrue($client->ping());

        self::stopWebServer();
        $this->assertFalse($client->ping());
    }
}
