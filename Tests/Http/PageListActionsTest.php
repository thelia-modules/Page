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
use Page\Model\PageQuery;
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

/**
 * The actions of the pages list (delete, visibility, homepage, position) read
 * the session token from the POST body and check the administrator's rights
 * on the Page module before changing anything.
 */
final class PageListActionsTest extends WebIntegrationTestCase
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
     * @return iterable<string, array{string, string}>
     */
    public static function actionProvider(): iterable
    {
        yield 'delete' => ['delete', AccessManager::DELETE];
        yield 'visibility' => ['visibility', AccessManager::UPDATE];
        yield 'homepage' => ['homepage', AccessManager::UPDATE];
        yield 'position' => ['position', AccessManager::UPDATE];
    }

    #[Test]
    #[DataProvider('actionProvider')]
    public function aTokenInTheQueryStringChangesNothing(string $action, string $access): void
    {
        $this->logIn($this->fixtures->admin());
        $token = $this->sessionToken();
        [$url, $fields, $unchanged] = $this->target($action);

        $this->client->request('POST', $url.'?'.http_build_query($fields + ['_token' => $token]));

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertTrue($unchanged());
    }

    #[Test]
    #[DataProvider('actionProvider')]
    public function aPostWithoutTheTokenChangesNothing(string $action, string $access): void
    {
        $this->logIn($this->fixtures->admin());
        $this->sessionToken();
        [$url, $fields, $unchanged] = $this->target($action);

        $this->client->request('POST', $url, $fields);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertTrue($unchanged());
    }

    #[Test]
    #[DataProvider('actionProvider')]
    public function anAdministratorWithoutTheRightChangesNothing(string $action, string $access): void
    {
        $this->logIn($this->fixtures->admin());
        $token = $this->sessionToken();
        [$url, $fields, $unchanged] = $this->target($action);

        $granted = array_values(array_diff([AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE], [$access]));
        $this->logIn($this->administratorWithModuleAccesses($granted));
        $this->client->request('POST', $url, $fields + ['_token' => $token]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertTrue($unchanged());
    }

    #[Test]
    #[DataProvider('actionProvider')]
    public function anAdministratorWithTheRightAndTheTokenPerformsTheAction(string $action, string $access): void
    {
        $this->logIn($this->fixtures->admin());
        $token = $this->sessionToken();
        [$url, $fields, $unchanged] = $this->target($action);

        $this->logIn($this->administratorWithModuleAccesses([AccessManager::VIEW, $access]));
        $this->client->request('POST', $url, $fields + ['_token' => $token]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertFalse($unchanged());
    }

    #[Test]
    public function theListRendersTheDeletionTokenInTheFormBody(): void
    {
        $this->logIn($this->fixtures->admin());
        $page = $this->page('Listed');

        $crawler = $this->client->request('GET', '/admin/page');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('[data-testid="page-delete-'.$page->getId().'"]')->form();
        self::assertStringNotContainsString('_token', $form->getUri());

        $this->client->submit($form);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertNull(PageQuery::create()->findPk($page->getId()));
    }

    /**
     * @return array{string, array<string, string>, callable(): bool}
     */
    private function target(string $action): array
    {
        $page = $this->page('Target');
        $id = $page->getId();

        return match ($action) {
            'delete' => [
                '/admin/page/delete/'.$id,
                [],
                static fn (): bool => null !== PageQuery::create()->findPk($id),
            ],
            'visibility' => [
                '/admin/page/set-visible',
                ['page_id' => (string) $id, 'visible' => '0'],
                static fn (): bool => 1 === PageQuery::create()->findPk($id)?->getVisible(),
            ],
            'homepage' => [
                '/admin/page/set-home',
                ['page_id' => (string) $id],
                static fn (): bool => true !== (bool) PageQuery::create()->findPk($id)?->getIsHome(),
            ],
            'position' => $this->positionTarget($page),
        };
    }

    /**
     * @return array{string, array<string, string>, callable(): bool}
     */
    private function positionTarget(PageModel $page): array
    {
        $sibling = $this->page('Sibling');
        $id = $sibling->getId();
        $left = PageQuery::create()->findPk($id)?->getTreeLeft();

        return [
            '/admin/page/update-position',
            ['page_id' => (string) $id, 'mode' => 'up'],
            static fn (): bool => PageQuery::create()->findPk($id)?->getTreeLeft() === $left,
        ];
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
        $this->page('Token holder');
        $crawler = $this->client->request('GET', '/admin/page');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $token = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');
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
