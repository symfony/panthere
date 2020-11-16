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
use Symfony\Component\Panther\ProcessManager\ChromeManager;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClientTest extends TestCase
{
    public function testCreateClient()
    {
        $client = self::createPantherClient();
        self::assertInstanceOf(AbstractBrowser::class, $client);
        self::assertInstanceOf(WebDriver::class, $client);
        self::assertInstanceOf(JavaScriptExecutor::class, $client);
        self::assertInstanceOf(KernelInterface::class, self::$kernel);
    }

    public function testWaitForEmptyLocator()
    {
        $this->expectException(InvalidSelectorException::class);

        $client = self::createPantherClient();
        $client->request('GET', '/waitfor.html');
        $client->waitFor('');
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitFor(string $locator)
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor.html');
        $c = $client->waitFor($locator);
        self::assertInstanceOf(Crawler::class, $c);
        self::assertSame('Hello', $crawler->filter('#hello')->text());
    }

    public function testWaitForHiddenInputElement(): void
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor-hidden-input.html');
        $c = $client->waitFor('#hello');
        self::assertInstanceOf(Crawler::class, $c);
        self::assertSame('Hello', $crawler->filter('#hello')->getAttribute('value'));
    }

    public function waitForDataProvider(): iterable
    {
        yield 'css selector' => ['locator' => '#hello'];
        yield 'xpath expression' => ['locator' => '//*[@id="hello"]'];
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForVisibility(string $locator)
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor-element-to-be-visible.html');
        $c = $client->waitForVisibility($locator);
        self::assertInstanceOf(Crawler::class, $c);
        self::assertSame('Hello', $crawler->filter('#hello')->text());
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForInvisibility(string $locator)
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor-element-to-be-invisible.html');
        $c = $client->waitForInvisibility($locator);
        self::assertInstanceOf(Crawler::class, $c);
        self::assertSame('', $crawler->filter('#hello')->text());
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForElementToContain(string $locator)
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor-element-to-contain.html');
        $c = $client->waitForElementToContain($locator, 'new content');
        self::assertInstanceOf(Crawler::class, $c);
        self::assertSame('Hello new content', $crawler->filter('#hello')->text());
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForElementToNotContain(string $locator)
    {
        $client = self::createPantherClient();
        $crawler = $client->request('GET', '/waitfor-element-to-not-contain.html');
        $c = $client->waitForElementToNotContain($locator, 'removed content');
        self::assertInstanceOf(Crawler::class, $c);
        self::assertSame('Hello', $crawler->filter('#hello')->text());
    }

    /**
     * @dataProvider waitForDataProvider
     */
    public function testWaitForStalenessElement(string $locator): void
    {
        $client = self::createPantherClient();
        $client->request('GET', '/waitfor-staleness.html');
        $crawler = $client->waitForStaleness($locator);
        self::assertInstanceOf(Crawler::class, $crawler);
    }

    public function testExecuteScript()
    {
        $client = self::createPantherClient();
        $client->request('GET', '/basic.html');
        $innerText = $client->executeScript('return document.querySelector(arguments[0]).innerText;', ['.p-1']);
        self::assertSame('P1', $innerText);
    }

    public function testExecuteScriptLogicExceptionWhenDriverIsNotStartedYet()
    {
        $this->expectException(\LogicException::class);
        $client = Client::createChromeClient();
        $client->executeScript('return document.querySelector(arguments[0]).innerText;', ['.p-1']);
    }

    public function testExecuteAsyncScript()
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

        self::assertSame('P1', $innerText);
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testGetCrawler(callable $clientFactory, string $type): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        self::assertInstanceOf(DomCrawlerCrawler::class, $crawler);
        if (Client::class === $type) {
            self::assertInstanceOf(Crawler::class, $crawler);
        }
    }

    public function testRefreshCrawler(): void
    {
        $client = self::createPantherClient();

        $crawler = $client->request('GET', '/js-redirect.html');
        $linkCrawler = $crawler->selectLink('Redirect Link');

        self::assertSame('Redirect Link', $linkCrawler->text());

        $client->click($linkCrawler->link());
        $client->wait(5)->until(WebDriverExpectedCondition::titleIs('A basic page'));

        $refreshedCrawler = $client->refreshCrawler();

        self::assertInstanceOf(Crawler::class, $refreshedCrawler);
        self::assertSame(self::$baseUri.'/basic.html', $refreshedCrawler->getUri());
        self::assertSame('Hello', $refreshedCrawler->filter('h1')->text());
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
        self::assertInstanceOf(DomCrawlerCrawler::class, $crawler);
        if (Client::class === $type) {
            self::assertInstanceOf(Crawler::class, $crawler);
        }
        self::assertSame(self::$baseUri.'/basic.html#e12', $crawler->getUri());
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
        self::assertInstanceOf(DomCrawlerCrawler::class, $crawler);
        if ($client instanceof Client) {
            self::assertInstanceOf(Crawler::class, $crawler);
        }
        self::assertSame(self::$baseUri.'/form-handle.php', $crawler->getUri());
        self::assertSame('I1: Reclus', $crawler->filter('#result')->text());

        $crawler = $client->back();
        $form = $crawler->filter('form')->eq(0)->form([
            'i1' => 'Michel',
        ]);

        $crawler = $client->submit($form);
        self::assertSame('I1: n/a', $crawler->filter('#result')->text());
        self::assertSame(self::$baseUri.'/form-handle.php?i1=Michel&i2=&i3=&i4=i4a', $crawler->getUri());
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
        self::assertInstanceOf(DomCrawlerCrawler::class, $crawler);
        if (Client::class === $type) {
            self::assertInstanceOf(Crawler::class, $crawler);
        }
        self::assertSame(self::$baseUri.'/form-handle.php', $crawler->getUri());
        self::assertSame('I1: Reclus', $crawler->filter('#result')->text());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testHistory(callable $clientFactory)
    {
        /** @var AbstractBrowser $client */
        $client = $clientFactory();
        $crawler = $client->request('GET', self::$baseUri.'/link.html');
        self::assertSame(self::$baseUri.'/link.html', $crawler->getUri());

        $crawler = $client->click($crawler->selectLink('E2')->link());
        self::assertSame(self::$baseUri.'/basic.html#e2', $crawler->getUri());

        $crawler = $client->back();
        self::assertSame(self::$baseUri.'/link.html', $crawler->getUri());

        $crawler = $client->forward();
        self::assertSame(self::$baseUri.'/basic.html#e2', $crawler->getUri());

        $crawler = $client->reload();
        self::assertSame(self::$baseUri.'/basic.html#e2', $crawler->getUri());

        $client->restart();
        $crawler = $client->request('GET', self::$baseUri.'/link.html');
        self::assertSame(self::$baseUri.'/link.html', $crawler->getUri());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testCookie(callable $clientFactory, string $type)
    {
        /** @var AbstractBrowser $client */
        $client = $clientFactory();
        $cookieJar = $client->getCookieJar();
        $cookieJar->clear(); // Firefox keeps the existing context by default, be sure to clear existing cookies

        $crawler = $client->request('GET', self::$baseUri.'/cookie.php');
        self::assertSame('0', $crawler->filter('#barcelona')->text());

        self::assertInstanceOf(BrowserKitCookieJar::class, $cookieJar);
        if (Client::class === $type) {
            self::assertInstanceOf(CookieJar::class, $cookieJar);
        }

        self::assertCount(1, $client->getCookieJar()->all());
        $cookie = $cookieJar->get('barcelona', '/cookie.php', '127.0.0.1');
        self::assertInstanceOf(Cookie::class, $cookie);
        self::assertSame('barcelona', $cookie->getName());
        self::assertSame('1', $cookie->getValue());
        self::assertNull($cookie->getExpiresTime());
        self::assertSame('/cookie.php', $cookie->getPath());
        self::assertSame('127.0.0.1', $cookie->getDomain());
        self::assertFalse($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());

        self::assertNull($cookieJar->get('barcelona', '/cookies.php', '127.0.0.1'));
        self::assertNull($cookieJar->get('barcelona', '/cookie.php', 'example.com'));
        self::assertNull($cookieJar->get('barcelona', '/', '127.0.0.1'));
        self::assertNotNull($cookieJar->get('barcelona', '/cookie.php'));
        self::assertNotNull($cookieJar->get('barcelona', '/cookie.php/bar', '127.0.0.1'));

        $crawler = $client->reload();
        self::assertSame('1', $crawler->filter('#barcelona')->text());

        self::assertNotEmpty($cookieJar->all());
        $cookieJar->clear();
        self::assertEmpty($cookieJar->all());

        $cookieJar->set(new Cookie('foo', 'bar'));
        $crawler = $client->reload();
        self::assertSame('bar', $cookieJar->get('foo')->getValue());
        self::assertSame('0', $crawler->filter('#barcelona')->text());
        self::assertSame('bar', $crawler->filter('#foo')->text());

        $cookieJar->expire('foo');
        self::assertNull($cookieJar->get('foo'));
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testServerPort(callable $clientFactory): void
    {
        $expectedPort = $_SERVER['PANTHER_WEB_SERVER_PORT'] ?? '9080';
        $clientFactory();
        self::assertEquals($expectedPort, \mb_substr(self::$baseUri, -4));
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
        self::assertStringContainsString($client->getBrowserManager() instanceof ChromeManager ? 'Chrome' : 'Firefox', $client->getPageSource());
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

        self::assertTrue($client->ping());

        self::stopWebServer();
        self::assertFalse($client->ping());
    }
}
