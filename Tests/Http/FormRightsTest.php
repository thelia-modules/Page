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
use Page\Model\PageI18nQuery;
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

/**
 * The create and update forms of pages, tags and types are saved only by an
 * administrator holding the matching access on the Page module.
 */
final class FormRightsTest extends WebIntegrationTestCase
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
    public static function formProvider(): iterable
    {
        yield 'page creation' => ['page-create', AccessManager::CREATE];
        yield 'page update' => ['page-update', AccessManager::UPDATE];
        yield 'page SEO update' => ['page-seo', AccessManager::UPDATE];
        yield 'tag creation' => ['tag-create', AccessManager::CREATE];
        yield 'tag update' => ['tag-update', AccessManager::UPDATE];
        yield 'type creation' => ['type-create', AccessManager::CREATE];
        yield 'type update' => ['type-update', AccessManager::UPDATE];
    }

    #[Test]
    #[DataProvider('formProvider')]
    public function anAdministratorWithoutTheAccessSavesNothing(string $form, string $access): void
    {
        $this->logIn($this->fixtures->admin());
        [$url, $values, $saved] = $this->submission($form);

        $granted = array_values(array_diff([AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE], [$access]));
        $this->logIn($this->administratorWithModuleAccesses($granted));
        $this->client->request('POST', $url, $values);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertFalse($saved());
    }

    #[Test]
    #[DataProvider('formProvider')]
    public function anAdministratorWithTheAccessSavesTheForm(string $form, string $access): void
    {
        $this->logIn($this->fixtures->admin());
        [$url, $values, $saved] = $this->submission($form);

        $this->logIn($this->administratorWithModuleAccesses([AccessManager::VIEW, $access]));
        $this->client->request('POST', $url, $values);

        self::assertLessThan(400, $this->client->getResponse()->getStatusCode());
        self::assertTrue($saved());
    }

    /**
     * Reads the form from its screen, fills it, and returns where it posts, what
     * it posts, and how to tell it was saved.
     *
     * @return array{string, array<string, mixed>, callable(): bool}
     */
    private function submission(string $form): array
    {
        $value = 'value-'.bin2hex(random_bytes(4));

        return match ($form) {
            'page-create' => $this->screenForm('/admin/page/new', 'page-create-form', ['#page_title' => $value], null,
                static fn (): bool => PageI18nQuery::create()->filterByTitle($value)->exists()),
            'page-update' => $this->screenForm('/admin/page/edit/'.$this->page()->getId(), 'page-edit-form', ['#edit_title' => $value], null,
                static fn (): bool => PageI18nQuery::create()->filterByTitle($value)->exists()),
            'page-seo' => $this->screenForm('/admin/page/edit/'.$this->page()->getId(), 'page-seo-form', ['#seo_meta_title' => $value, '#seo_url' => $value], null,
                static fn (): bool => PageI18nQuery::create()->filterByMetaTitle($value)->exists()),
            'tag-create' => $this->screenForm('/admin/page-tag/new', 'page-tag-create-form', ['#page_tag_field' => $value], null,
                static fn (): bool => PageTagQuery::create()->filterByTag($value)->exists()),
            'tag-update' => $this->screenForm('/admin/page-tag/edit/'.$this->tag()->getId(), 'page-tag-update-form', ['#page_tag_field' => $value], null,
                static fn (): bool => PageTagQuery::create()->filterByTag($value)->exists()),
            'type-create' => $this->screenForm('/admin/page-type', 'page-type-create-form', ['#page_type_input' => $value], null,
                static fn (): bool => PageTypeQuery::create()->filterByType($value)->exists()),
            // No screen edits a type: the creation form, same form class, is posted to the update route.
            'type-update' => $this->screenForm('/admin/page-type', 'page-type-create-form', ['#page_type_input' => $value], '/admin/page-type/update/'.$this->type()->getId(),
                static fn (): bool => PageTypeQuery::create()->filterByType($value)->exists()),
        };
    }

    /**
     * @param array<string, string> $fields input selector => value
     * @param callable(): bool      $saved
     *
     * @return array{string, array<string, mixed>, callable(): bool}
     */
    private function screenForm(string $screen, string $testId, array $fields, ?string $postTo, callable $saved): array
    {
        $crawler = $this->client->request('GET', $screen);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $formNode = $crawler->filter('form[data-testid="'.$testId.'"]');
        $form = $formNode->form();

        foreach ($fields as $selector => $value) {
            $form[(string) $formNode->filter($selector)->attr('name')] = $value;
        }

        return [$postTo ?? $form->getUri(), $form->getPhpValues(), $saved];
    }

    private function page(): PageModel
    {
        $root = PageQuery::create()->findRoot();

        if (null === $root) {
            $root = new PageModel();
            $root->safeMakeRoot('en_US')->save();
        }

        $page = new PageModel();
        $page->setLocale('en_US')
            ->setTitle('Edited page')
            ->setCode('page-'.bin2hex(random_bytes(4)))
            ->setVisible(1)
            ->insertAsLastChildOf($root)
            ->save();

        return $page;
    }

    private function tag(): PageTag
    {
        $tag = (new PageTag())->setTag('tag-'.bin2hex(random_bytes(4)));
        $tag->save();

        return $tag;
    }

    private function type(): PageType
    {
        $type = (new PageType())->setType('type-'.bin2hex(random_bytes(4)));
        $type->save();

        return $type;
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
