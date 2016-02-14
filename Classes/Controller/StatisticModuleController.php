<?php
namespace CommerceTeam\Commerce\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use CommerceTeam\Commerce\Utility\ConfigurationUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Template\ModuleTemplate;

/**
 * Module 'Statistics' for the commerce extension.
 *
 * Class \CommerceTeam\Commerce\Controller\StatisticModuleController
 *
 * @author 2004-2011 Joerg Sprung <jsp@marketing-factory.de>
 */
class StatisticModuleController extends \TYPO3\CMS\Backend\Module\BaseScriptClass
{
    /**
     * Document template.
     *
     * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
     */
    public $doc;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'commerce_statistic';

    /**
     * ModuleTemplate Container
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * Page information.
     *
     * @var array
     */
    protected $pageinfo;

    /**
     * Order page id.
     *
     * @var array
     */
    protected $orderPageId;

    /**
     * Statistics.
     *
     * @var \CommerceTeam\Commerce\Utility\StatisticsUtility
     */
    public $statistics;

    /**
     * Constructor
     *
     * @return self
     */
    public function __construct()
    {
        $this->moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->getLanguageService()->includeLLFile('EXT:lang/locallang_mod_web_list.xlf');
        $this->getLanguageService()->includeLLFile(
            'EXT:commerce/Resources/Private/Language/locallang_mod_statistic.xlf'
        );
        $this->MCONF = array(
            'name' => $this->moduleName,
        );
    }

    /**
     * Initialization.
     *
     * @return void
     */
    public function init()
    {
        parent::init();

        $this->statistics = GeneralUtility::makeInstance(\CommerceTeam\Commerce\Utility\StatisticsUtility::class);
        $this->statistics->init((int) ConfigurationUtility::getInstance()->getExtConf('excludeStatisticFolders'));

        $this->orderPageId = \CommerceTeam\Commerce\Utility\BackendUtility::getOrderFolderUid();

        /*
         * If we get an id via GP use this, else use the default id
         */
        $this->id = (int) GeneralUtility::_GP('id');
        if (!$this->id) {
            $this->id = $this->orderPageId;
        }

        $this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
        $this->doc->setModuleTemplate(PATH_TXCOMMERCE . 'Resources/Private/Backend/mod_index.html');

        $this->doc->form = '<form action="" method="POST" name="editform">';

        // JavaScript
        $this->doc->JScode = $this->doc->wrapScriptTags('
            script_ended = 0;
            function jumpToUrl(URL) {
                document.location = URL;
            }
        ');
        $this->doc->postCode = $this->doc->wrapScriptTags('
            script_ended = 1;
            if (top.fsMod) {
                top.fsMod.recentIds["web"] = ' . (int) $this->id . ';
            }
        ');
    }

    /**
     * Main function of the module. Write the content to $this->content.
     *
     * @return void
     */
    public function main()
    {
        $backendUser = $this->getBackendUser();
        $language = $this->getLanguageService();

        // Access check!
        // The page will show only if there is a valid page and if
        // this page may be viewed by the user
        $this->pageinfo = \TYPO3\CMS\Backend\Utility\BackendUtility::readPageAccess($this->id, $this->perms_clause);
        $access = is_array($this->pageinfo);

        $this->content = $this->doc->header($language->getLL('statistic'));

        // Checking access:
        if (($this->id && $access) || $backendUser->isAdmin()) {
            // Render content:
            $this->content .= '<h1>' . $this->getLanguageService()->sL($this->extClassConf['title']) . '</h1>';
            $this->extObjContent();
            $this->getButtons();
            $this->generateMenu();
        } else {
            // If no access or if ID == zero
            $this->content .= $this->doc->header($language->getLL('statistic'));
        }

        $docHeaderButtons = $this->getButtons();

        $markers = array(
            'CSH' => $docHeaderButtons['csh'],
            'CONTENT' => $this->content,
        );
        $markers['FUNC_MENU'] = $this->doc->funcMenu(
            '',
            \TYPO3\CMS\Backend\Utility\BackendUtility::getFuncMenu(
                $this->id,
                'SET[function]',
                $this->MOD_SETTINGS['function'],
                $this->MOD_MENU['function']
            )
        );
    }

    /**
     * Generates the menu based on $this->MOD_MENU
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function generateMenu()
    {
        $menu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('WebFuncJumpMenu');
        foreach ($this->MOD_MENU['function'] as $controller => $title) {
            $item = $menu
                ->makeMenuItem()
                ->setHref(
                    BackendUtility::getModuleUrl(
                        $this->moduleName,
                        [
                            'id' => $this->id,
                            'SET' => [
                                'function' => $controller
                            ]
                        ]
                    )
                )
                ->setTitle($title);
            if ($controller === $this->MOD_SETTINGS['function']) {
                $item->setActive(true);
            }
            $menu->addMenuItem($item);
        }
        $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    /**
     * Injects the request object for the current request or subrequest
     * Simply calls main() and init() and outputs the content
     *
     * @param ServerRequestInterface $request the current request
     * @param ResponseInterface $response
     * @return ResponseInterface the response with the content
     */
    public function mainAction(ServerRequestInterface $request, ResponseInterface $response)
    {
        $GLOBALS['SOBE'] = $this;
        $this->init();

        // Checking for first level external objects
        $this->checkExtObj();

        $this->main();

        $this->moduleTemplate->setContent($this->content);
        $response->getBody()->write($this->moduleTemplate->renderContent());
        return $response;
    }

    /**
     * Create the panel of buttons for submitting the form
     * or otherwise perform operations.
     *
     * @return array all available buttons as an assoc. array
     */
    protected function getButtons()
    {
        $backendUser = $this->getBackendUser();
        $language = $this->getLanguageService();

        $buttons = array(
            'csh' => '',
            // group left 1
            'level_up' => '',
            'back' => '',
            // group left 2
            'new_record' => '',
            'paste' => '',
            // group left 3
            'view' => '',
            'edit' => '',
            'move' => '',
            'hide_unhide' => '',
            // group left 4
            'csv' => '',
            'export' => '',
            // group right 1
            'cache' => '',
            'reload' => '',
            'shortcut' => '',
        );

        // CSH
        $buttons['csh'] = \TYPO3\CMS\Backend\Utility\BackendUtility::cshItem('_MOD_commerce_statistic', '');

        // Shortcut
        if ($backendUser->mayMakeShortcut()) {
            $buttons['shortcut'] = $this->doc->makeShortcutIcon(
                'id, edit_record, pointer, new_unique_uid, search_field, search_levels, showLimit',
                implode(',', array_keys($this->MOD_MENU)),
                $this->MCONF['name']
            );
        }

        // If access to Web>List for user, then link to that module.
        if ($backendUser->check('modules', 'web_list')) {
            // @todo fix to index.php entry point
            $href = 'db_list.php?id=' . $this->pageinfo['uid'] . '&returnUrl=' .
                rawurlencode(\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('REQUEST_URI'));
            $buttons['record_list'] = '<a href="' . htmlspecialchars($href) . '">' .
                \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon(
                    'apps-filetree-folder-list',
                    array('title' => $language->sL('LLL:EXT:lang/locallang_core.php:labels.showList', 1))
                ) . '</a>';
        }

        return $buttons;
    }
}
