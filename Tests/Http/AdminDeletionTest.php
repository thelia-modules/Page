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
use Page\Model\PageTag;
use Page\Model\PageTagQuery;
use Page\Model\PageType;
use Page\Model\PageTypeQuery;
use Page\Page;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\Admin;
use Thelia\Model\ProfileModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\Model\LibraryItemImage;
use TheliaLibrary\Model\LibraryItemImageQuery;

/**
 * The delete actions of the Page back-office answer a POST carrying the session
 * token, sent by an administrator allowed to delete in the module, and remove
 * nothing otherwise.
 */
final class AdminDeletionTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    private FixtureFactory $fixtures;

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
        Propel::enableInstancePooling();
        $this->injector->clear();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function deletionProvider(): iterable
    {
        yield 'tag' => ['tag'];
        yield 'type' => ['type'];
        yield 'document' => ['document'];
        yield 'image' => ['image'];
    }

    #[Test]
    #[DataProvider('deletionProvider')]
    public function aGetRequestDeletesNothing(string $kind): void
    {
        $this->logIn($this->fixtures->admin());
        [$url, $stillThere] = $this->target($kind);

        $this->client->request('GET', $url);

        // The router chain answers a method it has no route for with a 404.
        self::assertContains($this->client->getResponse()->getStatusCode(), [404, 405]);
        self::assertTrue($stillThere());
    }

    #[Test]
    #[DataProvider('deletionProvider')]
    public function aPostWithoutTheSessionTokenDeletesNothing(string $kind): void
    {
        $this->logIn($this->fixtures->admin());
        $this->sessionToken();
        [$url, $stillThere] = $this->target($kind);

        $this->client->request('POST', $url);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertTrue($stillThere());
    }

    #[Test]
    #[DataProvider('deletionProvider')]
    public function aPostWithTheTokenInTheQueryStringDeletesNothing(string $kind): void
    {
        $this->logIn($this->fixtures->admin());
        $token = $this->sessionToken();
        [$url, $stillThere] = $this->target($kind);

        $this->client->request('POST', $url.'?_token='.$token);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertTrue($stillThere());
    }

    #[Test]
    #[DataProvider('deletionProvider')]
    public function anAdministratorWhoCannotDeleteInTheModuleDeletesNothing(string $kind): void
    {
        $this->logIn($this->fixtures->admin());
        $token = $this->sessionToken();
        [$url, $stillThere] = $this->target($kind);

        $this->logIn($this->administratorWhoMayNotDelete());
        $this->client->request('POST', $url, ['_token' => $token]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertTrue($stillThere());
    }

    #[Test]
    #[DataProvider('deletionProvider')]
    public function anAdministratorAllowedToDeleteRemovesTheRecord(string $kind): void
    {
        $this->logIn($this->fixtures->admin());
        $token = $this->sessionToken();
        [$url, $stillThere] = $this->target($kind);

        $this->client->request('POST', $url, ['_token' => $token]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertFalse($stillThere());
    }

    #[Test]
    public function theImageListRendersEachDeletionAsATokenizedForm(): void
    {
        $this->logIn($this->fixtures->admin());
        $page = $this->page('With an image');
        $itemImage = $this->pageImage($page);

        $crawler = $this->client->request('POST', '/admin/page/image/list/'.$page->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('[data-testid="page-image-delete-'.$itemImage->getId().'"]')->form();
        self::assertSame('POST', $form->getMethod());
        self::assertNotSame('', (string) $form->get('_token')->getValue());
        self::assertStringNotContainsString('_token', $form->getUri());

        $this->client->submit($form);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertNull(LibraryItemImageQuery::create()->findPk($itemImage->getId()));
    }

    #[Test]
    public function theTagListRendersEachDeletionAsATokenizedForm(): void
    {
        $this->logIn($this->fixtures->admin());
        $tag = $this->tag();

        $crawler = $this->client->request('GET', '/admin/page-tag');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('[data-testid="page-tag-delete-'.$tag->getId().'"]')->form();
        self::assertSame('POST', $form->getMethod());
        self::assertStringNotContainsString('_token', $form->getUri());

        $this->client->submit($form);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertNull(PageTagQuery::create()->findPk($tag->getId()));
    }

    /**
     * @return array{string, callable(): bool}
     */
    private function target(string $kind): array
    {
        return match ($kind) {
            'tag' => $this->tagTarget(),
            'type' => $this->typeTarget(),
            'document' => $this->documentTarget(),
            'image' => $this->imageTarget(),
        };
    }

    /**
     * @return array{string, callable(): bool}
     */
    private function tagTarget(): array
    {
        $id = $this->tag()->getId();

        return ['/admin/page-tag/delete/'.$id, static fn (): bool => null !== PageTagQuery::create()->findPk($id)];
    }

    /**
     * @return array{string, callable(): bool}
     */
    private function typeTarget(): array
    {
        $type = (new PageType())->setType('Landing');
        $type->save();
        $id = $type->getId();

        return ['/admin/page-type/delete/'.$id, static fn (): bool => null !== PageTypeQuery::create()->findPk($id)];
    }

    /**
     * @return array{string, callable(): bool}
     */
    private function documentTarget(): array
    {
        $page = $this->page('With a document');
        $document = (new PageDocument())
            ->setPageId($page->getId())
            ->setLocale('en_US')
            ->setFile('missing-on-disk.pdf')
            ->setTitle('Brochure');
        $document->save();
        $id = $document->getId();

        return [
            '/admin/page/document/delete/'.$id.'/'.$page->getId(),
            static fn (): bool => null !== PageDocumentQuery::create()->findPk($id),
        ];
    }

    /**
     * @return array{string, callable(): bool}
     */
    private function imageTarget(): array
    {
        $page = $this->page('With an image');
        $itemImage = $this->pageImage($page);
        $id = $itemImage->getId();

        return [
            '/admin/page/image/delete/'.$id.'/'.$page->getId(),
            static fn (): bool => null !== LibraryItemImageQuery::create()->findPk($id),
        ];
    }

    private function tag(): PageTag
    {
        $tag = (new PageTag())->setTag('tag-'.bin2hex(random_bytes(4)));
        $tag->save();

        return $tag;
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

    private function pageImage(PageModel $page): LibraryItemImage
    {
        $image = (new LibraryImage())->setLocale('en_US')->setTitle('Picture');
        $image->save();

        return $this->associate($image, 'page', $page->getId());
    }

    private function associate(LibraryImage $image, string $itemType, int $itemId): LibraryItemImage
    {
        $itemImage = (new LibraryItemImage())
            ->setImageId($image->getId())
            ->setItemType($itemType)
            ->setItemId($itemId)
            ->setVisible(1)
            ->setPosition(1);
        $itemImage->save();

        return $itemImage;
    }

    /**
     * Opens a back-office screen that renders a deletion form, so the session
     * holds the token those forms carry, and returns it.
     */
    private function sessionToken(): string
    {
        $this->tag();
        $crawler = $this->client->request('GET', '/admin/page-tag');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $token = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');
        self::assertNotSame('', $token);

        return $token;
    }

    private function administratorWhoMayNotDelete(): Admin
    {
        $admin = $this->fixtures->restrictedAdmin([]);

        $access = new AccessManager(0);
        $access->build([AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE]);

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
