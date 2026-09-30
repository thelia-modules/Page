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
use Page\Model\PageDocument;
use Page\Model\PageDocumentQuery;
use Page\Model\PageQuery;
use Page\Page;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\Propel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\Admin;
use Thelia\Model\ProfileModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;
use TheliaLibrary\Model\LibraryItemImageQuery;
use TheliaLibrary\TheliaLibrary;

/**
 * Uploading an image or a document to a page, and reordering its documents,
 * need the session token in the POST body and the right to update in the
 * Page module.
 */
final class MediaUploadAndPositionTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    private FixtureFactory $fixtures;

    /** @var list<string> */
    private array $createdFiles = [];

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
        (new Filesystem())->remove($this->createdFiles);
        Propel::enableInstancePooling();
        $this->injector->clear();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function uploadProvider(): iterable
    {
        yield 'image' => ['image'];
        yield 'document' => ['document'];
    }

    #[Test]
    #[DataProvider('uploadProvider')]
    public function anUploadWithoutTheTokenAddsNothing(string $kind): void
    {
        $this->logIn($this->fixtures->admin());
        $this->sessionToken();
        $page = $this->page('Upload target');

        $this->client->request('POST', '/admin/page/'.$kind.'/upload/'.$page->getId(), [], ['file' => $this->upload()]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, $this->mediaCount($kind, $page));
    }

    #[Test]
    #[DataProvider('uploadProvider')]
    public function anAdministratorWhoCannotUpdateTheModuleUploadsNothing(string $kind): void
    {
        $this->logIn($this->fixtures->admin());
        $token = $this->sessionToken();
        $page = $this->page('Upload target');

        $this->logIn($this->administratorWithModuleAccesses([AccessManager::VIEW, AccessManager::CREATE, AccessManager::DELETE]));
        $this->client->request('POST', '/admin/page/'.$kind.'/upload/'.$page->getId(), ['_token' => $token], ['file' => $this->upload()]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, $this->mediaCount($kind, $page));
    }

    #[Test]
    #[DataProvider('uploadProvider')]
    public function theUploadFormCarriesTheToken(string $kind): void
    {
        $this->logIn($this->fixtures->admin());
        $page = $this->page('Upload target');

        $crawler = $this->client->request('GET', '/admin/page/edit/'.$page->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $token = (string) $crawler->filter('[data-testid="page-'.$kind.'-upload-form"] input[name="_token"]')->attr('value');
        self::assertNotSame('', $token);

        $this->client->request('POST', '/admin/page/'.$kind.'/upload/'.$page->getId(), ['_token' => $token], ['file' => $this->upload()]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame(1, $this->mediaCount($kind, $page));
        $this->rememberStoredFiles($kind, $page);
    }

    #[Test]
    public function aRefusedImageUploadAnswersAJsonError(): void
    {
        $this->logIn($this->fixtures->admin());
        $token = $this->sessionToken();
        $page = $this->page('Upload target');
        $path = tempnam(sys_get_temp_dir(), 'page-upload');
        self::assertIsString($path);
        file_put_contents($path, 'not an image');
        $this->createdFiles[] = $path;

        $this->client->request('POST', '/admin/page/image/upload/'.$page->getId(), ['_token' => $token], ['file' => new UploadedFile($path, 'notes.txt', 'text/plain', null, true)]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertFalse(json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['status']);
        self::assertSame(0, $this->mediaCount('image', $page));
    }

    #[Test]
    public function reorderingDocumentsWithoutTheTokenChangesNothing(): void
    {
        $this->logIn($this->fixtures->admin());
        $this->sessionToken();
        $page = $this->page('Documents');
        $this->document($page, 'first.pdf');
        $second = $this->document($page, 'second.pdf');

        $this->client->request('POST', '/admin/page/document/update-position/'.$page->getId(), ['document_id' => $second->getId(), 'position' => 1]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame(2, PageDocumentQuery::create()->findPk($second->getId())?->getPosition());
    }

    #[Test]
    public function aDocumentOfAnotherPageIsNotReordered(): void
    {
        $this->logIn($this->fixtures->admin());
        $token = $this->sessionToken();
        $page = $this->page('Documents');
        $this->document($page, 'first.pdf');
        $second = $this->document($page, 'second.pdf');
        $otherPage = $this->page('Other');

        $this->client->request('POST', '/admin/page/document/update-position/'.$otherPage->getId(), ['_token' => $token, 'document_id' => $second->getId(), 'position' => 1]);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        self::assertSame(2, PageDocumentQuery::create()->findPk($second->getId())?->getPosition());
    }

    #[Test]
    public function aDocumentIsReorderedWithTheTokenAndTheRight(): void
    {
        $this->logIn($this->fixtures->admin());
        $token = $this->sessionToken();
        $page = $this->page('Documents');
        $this->document($page, 'first.pdf');
        $second = $this->document($page, 'second.pdf');

        $this->client->request('POST', '/admin/page/document/update-position/'.$page->getId(), ['_token' => $token, 'document_id' => $second->getId(), 'position' => 1]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame(1, PageDocumentQuery::create()->findPk($second->getId())?->getPosition());
    }

    private function mediaCount(string $kind, PageModel $page): int
    {
        return 'image' === $kind
            ? LibraryItemImageQuery::create()->filterByItemType('page')->filterByItemId($page->getId())->count()
            : PageDocumentQuery::create()->filterByPageId($page->getId())->count();
    }

    private function rememberStoredFiles(string $kind, PageModel $page): void
    {
        if ('image' === $kind) {
            foreach (LibraryItemImageQuery::create()->filterByItemType('page')->filterByItemId($page->getId())->find() as $itemImage) {
                $fileName = $itemImage->getLibraryImage()?->getFileName();
                if (null !== $fileName) {
                    $this->createdFiles[] = TheliaLibrary::getImageDirectory().$fileName;
                }
            }

            return;
        }

        foreach (PageDocumentQuery::create()->filterByPageId($page->getId())->find() as $document) {
            $fileName = $document->setLocale('en_US')->getFile();
            $this->createdFiles[] = Page::getDocumentsUploadDir().\DIRECTORY_SEPARATOR.$fileName;
            $this->createdFiles[] = Page::getImagesUploadDir().\DIRECTORY_SEPARATOR.$fileName.'.jpg';
        }
    }

    private function upload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'page-upload');
        self::assertIsString($path);
        // A 1x1 transparent PNG: a valid image for the image route, a harmless file for the document one.
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true));
        $this->createdFiles[] = $path;

        return new UploadedFile($path, 'pixel.png', 'image/png', null, true);
    }

    private function document(PageModel $page, string $file): PageDocument
    {
        $document = (new PageDocument())
            ->setPageId($page->getId())
            ->setLocale('en_US')
            ->setFile($file)
            ->setTitle($file);
        $document->save();

        return $document;
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

        return $page;
    }

    private function sessionToken(): string
    {
        $page = $this->page('Token holder');
        $crawler = $this->client->request('GET', '/admin/page/edit/'.$page->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $token = (string) $crawler->filter('[data-testid="page-image-upload-form"] input[name="_token"]')->attr('value');
        self::assertNotSame('', $token);

        return $token;
    }

    /**
     * @param list<string> $accesses
     */
    private function administratorWithModuleAccesses(array $accesses): Admin
    {
        $admin = $this->fixtures->restrictedAdmin([]);

        $access = new AccessManager(0);
        $access->build($accesses);

        (new ProfileModule())
            ->setProfileId($admin->getProfileId())
            ->setModuleId(Page::getModuleId())
            ->setAccess($access->getAccessValue())
            ->save();

        return $admin;
    }

    private function logIn(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }
}
