<?php

namespace Page;

use Page\Model\PageQuery;
use Propel\Runtime\Connection\ConnectionInterface;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Install\Database;
use Thelia\Model\ConfigQuery;
use Thelia\Module\BaseModule;

class Page extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'page';

    public function getHooks()
    {
        return [
            [
                'type' => TemplateDefinition::BACK_OFFICE,
                'code' => 'page.tab-content',
                'title' => 'Hook page tab content',
                'description' => 'Hook page tab content',
                'active' => true,
            ],
        ];
    }

    public function postActivation(ConnectionInterface $con = null): void
    {
        if (!$this->getConfigValue('extension_black_listed', null)) {
            $this->setConfigValue('extension_black_listed',
                implode(',',
                    [
                        'php',
                        'php3',
                        'php4',
                        'php5',
                        'php6',
                        'asp',
                        'aspx'
                    ]
                )
            );
        }

        if (!$this->getConfigValue('is_initialized', false)) {
            $database = new Database($con);

            $database->insertSql(null, [__DIR__ . '/Config/TheliaMain.sql']);

            $this->setConfigValue('is_initialized', true);
        }
    }

    /**
     * @return string
     */
    public static function getDocumentsUploadDir(): string
    {
        if (!$uploadDir = ConfigQuery::read('documents_library_path')) {
            $uploadDir = THELIA_LOCAL_DIR . 'media' . DS . 'documents';
        }

        return THELIA_ROOT . $uploadDir . DS . self::DOMAIN_NAME;
    }

    /**
     * Defines how services are loaded in your modules
     *
     * @param ServicesConfigurator $servicesConfigurator
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode() . '\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()) . "/I18n/*"])
            ->autowire()
            ->autoconfigure();
    }

    /**
     * Execute sql files in Config/update/ folder named with module version (ex: 1.0.1.sql).
     *
     * @param $currentVersion
     * @param $newVersion
     * @param ConnectionInterface|null $con
     */
    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        $finder = Finder::create()
            ->name('*.sql')
            ->depth(0)
            ->sortByName()
            ->in(__DIR__ . DS . 'Config' . DS . 'update');

        $database = new Database($con);

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }

        if ($newVersion === "1.1.2") {
            $database->insertSql(null, [__DIR__ . DS . 'Config' . DS . 'update'. DS .'nested_sets_1.sql']);

            $pages = PageQuery::create()
                ->orderByPosition()
                ->find();

            $pageRoot = new \Page\Model\Page();
            $pageRoot->setCode('root');
            $pageRoot->makeRoot();
            $pageRoot->save();

            foreach ($pages as $page) {
                $page->insertAsLastChildOf($pageRoot);
                $page->save();
            }

            $database->insertSql(null, [__DIR__ . DS . 'Config' . DS . 'update'. DS .'nested_sets_2.sql']);
        }
    }
}
