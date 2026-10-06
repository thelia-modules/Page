<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Page\Tests\Http;

use Page\Model\Page as PageModel;
use Page\Model\PageDocumentQuery;
use Page\Model\PageQuery;
use Page\Page;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\Propel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\Admin;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * A page document is published as is under the shop domain: what a web
 * server would run is never stored, whatever name it is sent under.
 */
final class DocumentTypesTest extends WebIntegrationTestCase
{
    private const CONTENT = 'stored as sent';

    private AdminSessionInjector $injector;

    private FixtureFactory $fixtures;

    /** @var list<string> */
    private array $createdFiles = [];

    /** @var list<PageModel> */
    private array $pages = [];

    private ?string $configuredBlackList = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getContainer()->get(EventDispatcherInterface::class)->addSubscriber($this->injector);
        $this->fixtures = new FixtureFactory(Propel::getConnection('TheliaMain'));

        (new \ReflectionProperty(ParserResolver::class, 'currentParser'))->setValue(null, null);
        Propel::disableInstancePooling();
    }

    protected function tearDown(): void
    {
        if (null !== $this->configuredBlackList) {
            Page::setConfigValue('extension_black_listed', $this->configuredBlackList);
        }

        foreach ($this->pages as $page) {
            $this->storedFilesOf($page);
        }

        (new Filesystem())->remove($this->createdFiles);
        Propel::enableInstancePooling();
        $this->injector->clear();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nameStoredUnderARefusedExtensionProvider(): iterable
    {
        yield 'trailing dot after php' => ['report.php .'];
        yield 'trailing space after php' => ['report.php '];
        yield 'space inside php' => ['report.ph p'];
        yield 'hash inside php' => ['report.p#hp'];
        yield 'shtml' => ['include.shtml'];
    }

    #[Test]
    #[DataProvider('nameStoredUnderARefusedExtensionProvider')]
    public function aNameStoredUnderARefusedExtensionIsRefused(string $name): void
    {
        $page = $this->page('Documents');

        $this->uploadDocument($page, $name, self::CONTENT);

        self::assertSame(415, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, PageDocumentQuery::create()->filterByPageId($page->getId())->count());
        self::assertSame([], $this->storedFilesOf($page));
    }

    #[Test]
    public function theConfiguredExtensionsApplyToTheStoredName(): void
    {
        $this->configuredBlackList = (string) Page::getConfigValue('extension_black_listed', '');
        Page::setConfigValue('extension_black_listed', 'zip');
        $page = $this->page('Documents');

        $this->uploadDocument($page, 'archive.zip .', 'PK');

        self::assertSame(415, $this->client->getResponse()->getStatusCode());
        self::assertSame([], $this->storedFilesOf($page));
    }

    #[Test]
    public function aPdfIsStoredAsSent(): void
    {
        $page = $this->page('Documents');

        $this->uploadDocument($page, 'Price list.pdf', '%PDF-1.4 price list');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $stored = $this->storedFilesOf($page);
        self::assertSame(['pricelist'.$page->getId().'.pdf'], array_map('basename', $stored));
        self::assertSame('%PDF-1.4 price list', file_get_contents($stored[0]));
    }

    private function uploadDocument(PageModel $page, string $name, string $content): void
    {
        $this->logIn($this->fixtures->admin());
        $token = $this->sessionToken($page);

        $path = tempnam(sys_get_temp_dir(), 'page-document');
        self::assertIsString($path);
        file_put_contents($path, $content);
        $this->createdFiles[] = $path;

        $this->client->request(
            'POST',
            '/admin/page/document/upload/'.$page->getId(),
            ['_token' => $token],
            ['file' => new UploadedFile($path, $name, 'application/octet-stream', null, true)],
        );
    }

    /**
     * The files of the documents directory whose stored name carries the page id.
     *
     * @return list<string>
     */
    private function storedFilesOf(PageModel $page): array
    {
        $directory = Page::getDocumentsUploadDir();
        $files = [];

        foreach (is_dir($directory) ? (scandir($directory) ?: []) : [] as $file) {
            if (1 === preg_match('/(?<=[a-z.])'.$page->getId().'(?![0-9])/', $file)) {
                $files[] = $directory.\DIRECTORY_SEPARATOR.$file;
                $this->createdFiles[] = $directory.\DIRECTORY_SEPARATOR.$file;
                $this->createdFiles[] = Page::getImagesUploadDir().\DIRECTORY_SEPARATOR.$file.'.jpg';
            }
        }

        return $files;
    }

    private function page(string $title): PageModel
    {
        $root = PageQuery::create()->findRoot();

        if (null === $root) {
            $root = new PageModel();
            $root->safeMakeRoot('en_US')->save();
        }

        $page = new PageModel();
        $page->setLocale('en_US')
            ->setTitle($title)
            ->setCode('page-'.bin2hex(random_bytes(4)))
            ->setVisible(1)
            ->insertAsLastChildOf($root)
            ->save();
        $this->pages[] = $page;

        return $page;
    }

    private function sessionToken(PageModel $page): string
    {
        $crawler = $this->client->request('GET', '/admin/page/edit/'.$page->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $token = (string) $crawler->filter('[data-testid="page-document-upload-form"] input[name="_token"]')->attr('value');
        self::assertNotSame('', $token);

        return $token;
    }

    private function logIn(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }
}
