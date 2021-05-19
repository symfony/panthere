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

namespace Symfony\Component\Panther\Tests\DomCrawler;

use Facebook\WebDriver\WebDriverElement;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\Client as PantherClient;
use Symfony\Component\Panther\DomCrawler\Image;
use Symfony\Component\Panther\DomCrawler\Link;
use Symfony\Component\Panther\Tests\TestCase;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CrawlerTest extends TestCase
{
    public function testCreateCrawler(): void
    {
        $crawler = self::createPantherClient()->request('GET', self::$baseUri.'/basic.html');
        $this->assertInstanceOf(Crawler::class, $crawler);
        $this->assertInstanceOf(WebDriverElement::class, $crawler);
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testGetUri(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        $this->assertSame(static::$baseUri.'/basic.html', $crawler->getUri());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testHtml(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        $this->assertStringContainsString('<title>A basic page</title>', $crawler->html());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testIterate(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        foreach ($crawler as $element) {
            $this->assertEquals('html', $element instanceof \DOMElement ? $element->tagName : $element->getTagName());
        }
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testFilterXpath(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        $crawler->filterXPath('descendant-or-self::body/p')->each(function (Crawler $crawler, int $i) {
            switch ($i) {
                case 0:
                    $this->assertSame('P1', $crawler->text());
                    break;
                case 1:
                    $this->assertSame('P2', $crawler->text());
                    break;
                case 2:
                    $this->assertSame('36', $crawler->text());
                    break;
                default:
                    $this->fail(\sprintf('Unexpected index "%d".', $i));
            }
        });
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testFilter(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        $this->assertCount(3, $crawler->filter('main > p'));

        $texts = [];
        $crawler->filter('main > p')->each(function (Crawler $crawler, int $i) use (&$texts) {
            $texts[$i] = $crawler->text();
        });
        $this->assertSame(['Sibling', 'Sibling 2', 'Sibling 3'], $texts);
        $this->assertSame('Sibling 2', $crawler->filter('main')->filter('#a-sibling')->text());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testReduce(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        $crawler->filter('main > p')->reduce(function (Crawler $crawler) {
            return 'a-sibling' === $crawler->attr('id');
        })->each(function (Crawler $crawler) {
            $this->assertSame('p', $crawler->nodeName());
        });
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testEq(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        $this->assertSame('a-sibling', $crawler->filter('main > p')->eq(1)->attr('id'));
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testFirst(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        $this->assertSame('Sibling', $crawler->filter('main > p')->first()->text());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testLast(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        $this->assertSame('Sibling 3', $crawler->filter('main > p')->last()->text());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testSiblings(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');

        $texts = [];
        $crawler->filter('main > p')->siblings()->each(function (Crawler $c, int $i) use (&$texts) {
            $texts[$i] = $c->text();
        });

        $this->assertSame(['Main', 'Sibling 2', 'Sibling 3'], $texts);
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testNextAll(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');

        $texts = [];
        $crawler->filter('main > p')->nextAll()->each(function (Crawler $c, int $i) use (&$texts) {
            $texts[$i] = $c->text();
        });

        $this->assertSame(['Sibling 2', 'Sibling 3'], $texts);
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testPreviousAll(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');

        $texts = [];
        $crawler->filter('main > p')->previousAll()->each(function (Crawler $c, int $i) use (&$texts) {
            $texts[$i] = $c->text();
        });

        $this->assertSame(['Main'], $texts);
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testChildren(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');

        $names = [];
        $crawler->filter('body')->children()->each(function (Crawler $c, int $i) use (&$names) {
            $names[$i] = $c->nodeName();
        });

        $this->assertSame(['h1', 'main', 'p', 'p', 'input', 'p'], $names);
    }

    /**
     * @dataProvider clientFactoryProvider
     *
     * @param mixed $clientFactory
     */
    public function testChildrenFilter($clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');

        $names = [];
        $crawler->filter('body')->children('p')->each(function (Crawler $c, int $i) use (&$names) {
            $names[$i] = $c->nodeName();
        });

        $this->assertSame(['p', 'p', 'p'], $names);
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testParents(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');

        $names = [];
        $crawler->filter('main > h1')->parents()->each(function (Crawler $c, int $i) use (&$names) {
            $names[$i] = $c->nodeName();
        });

        $this->assertSame(['main', 'body', 'html'], $names);
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testExtract(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');

        $this->assertSame([['', 'Sibling'], ['foo', 'Sibling 2'], ['', 'Sibling 3']], $crawler->filter('main > p')->extract(['class', '_text']));

        // Uncomment when https://github.com/symfony/symfony/pull/26433 will be merged
        $this->assertSame([[], [], []], $crawler->filter('main > p')->extract([]));
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testLink(callable $clientFactory, string $type): void
    {
        $crawler = $this->request($clientFactory, '/link.html');

        $links = $crawler->selectLink('E2');
        $this->assertCount(2, $links);

        $uris = [];
        foreach ($links->links() as $link) {
            $uris[] = $link->getUri();
        }
        $this->assertSame([self::$baseUri.'/basic.html#e2', self::$baseUri.'/basic.html#e22'], $uris);

        $link = $links->link();
        $this->assertInstanceOf(\Symfony\Component\DomCrawler\Link::class, $link);
        if (Client::class === $type) {
            $this->assertInstanceOf(Link::class, $link);
        }

        $this->assertSame('GET', $link->getMethod());
        $this->assertSame(self::$baseUri.'/basic.html#e2', $link->getUri());

        $link = $crawler->selectLink('API Platform')->link();
        $this->assertSame('https://api-platform.com/', $link->getUri());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testImage(callable $clientFactory, string $type): void
    {
        $crawler = $this->request($clientFactory, '/link.html');

        $images = $crawler->selectImage('API Platform');
        $this->assertCount(2, $images);

        $uris = [];
        foreach ($images->images() as $image) {
            $uris[] = $image->getUri();
        }
        $this->assertSame(['https://api-platform.com/logo-250x250.png', 'https://avatars0.githubusercontent.com/u/13420081'], $uris);

        $image = $images->image();
        $this->assertInstanceOf(\Symfony\Component\DomCrawler\Image::class, $image);
        if (Client::class === $type) {
            $this->assertInstanceOf(Image::class, $image);
        }

        $this->assertSame('GET', $image->getMethod());
        $this->assertSame('https://api-platform.com/logo-250x250.png', $image->getUri());
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testTextDefault(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        $this->assertSame('default', $crawler->filter('header')->text('default'));
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testHtmlDefault(callable $clientFactory): void
    {
        $crawler = $this->request($clientFactory, '/basic.html');
        $this->assertSame('default', $crawler->filter('header')->html('default'));
    }

    /**
     * @dataProvider clientFactoryProvider
     */
    public function testNormalizeText(callable $clientFactory, string $clientClass): void
    {
        if (PantherClient::class !== $clientClass) {
            $this->markTestSkipped('Need https://github.com/symfony/symfony/pull/34151');
        }

        $crawler = $this->request($clientFactory, '/normalize.html');
        $this->assertSame('Foo Bar Baz', $crawler->filter('#normalize')->text());
    }

    public function testDoNotNormalizeText(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::createPantherClient()->request('GET', self::$baseUri.'/normalize.html')->filter('#normalize')->text(null, false);
    }

    public function testCrawlerStillWorksAfterClientDestruction(): void
    {
        $client = Client::createChromeClient();
        self::startWebServer();

        $crawler = $client->request('GET', self::$baseUri.'/basic.html');
        unset($client);

        $this->assertStringContainsString('<title>A basic page</title>', $crawler->html());
    }
}
