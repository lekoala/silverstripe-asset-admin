<?php

namespace SilverStripe\AssetAdmin\Controller;

use SilverStripe\Filesystem\Storage\AssetNameGenerator;
use SilverStripe\AssetAdmin\FormField\AssetGalleryField;
use SilverStripe\AssetAdmin\FormField\DropzoneUploadField;
use LeftAndMain;
use PermissionProvider;
use DateField;
use TabSet;
use Tab;
use DropdownField;
use SS_HTTPResponse_Exception;
use Controller;
use TextField;
use FieldList;
use Form;
use FormAction;
use CheckboxField;
use Convert;
use ArrayData;
use File;
use Session;
use Requirements;
use CMSBatchActionHandler;
use HiddenField;
use DataObject;
use Injector;
use Folder;
use Security;
use CMSForm;
use CMSBatchAction;
use SS_List;
use CompositeField;
use SSViewer;
use HeaderField;
use FieldGroup;
use Object;

/**
 * AssetAdmin is the 'file store' section of the CMS.
 * It provides an interface for manipulating the File and Folder objects in the system.
 *
 * @package cms
 * @subpackage assets
 */
class AssetAdmin extends LeftAndMain implements PermissionProvider
{
    private static $url_segment = 'assets';

    private static $url_rule = '/$Action/$ID';

    private static $menu_title = 'Files';

    private static $tree_class = 'Folder';


    private static $url_handlers = [
        // Legacy redirect for SS3-style detail view
        'EditForm/field/File/item/$FileID/$Action' => 'legacyRedirectForEditView',
        // Pass all URLs to the index, for React to unpack
        'show/$FolderID/edit/$FileID' => 'index',
    ];

    /**
     * Amount of results showing on a single page.
     *
     * @config
     * @var int
     */
    private static $page_length = 15;

    /**
     * @config
     * @see Upload->allowedMaxFileSize
     * @var int
     */
    private static $allowed_max_file_size;

    private static $allowed_actions = array(
        'addfolder',
        'delete',
        'AddForm',
        'SearchForm',
        'legacyRedirectForEditView',
    );



	public function getClientConfig() {
		return array_merge( parent::getClientConfig(), [
            'assetsRoute' => $this->Link() . ':folderAction?/:folderId?/:fileAction?/:fileId?',
            'assetsRouteHome' => $this->Link() . 'show/0',
        ]);
	}

    public function legacyRedirectForEditView($request)
    {
        $fileID = $request->param('FileID');
        $file = File::get()->byID($fileID);
        $link = $this->getFileEditLink($file) ?: $this->Link();
        $this->redirect($link);
    }

    /**
     * Given a file return the CMS link to edit it
     *
     * @param File $file
     * @return string
     */
    public function getFileEditLink($file) {
        if(!$file || !$file->isInDB()) {
            return null;
        }
        $fileID = $file->ID;
        $folderID = $file->ParentID;
        return Controller::join_links(
            $this->Link('show'),
            $folderID,
            'edit',
            $fileID
        );
    }

    /**
     * Return fake-ID "root" if no ID is found (needed to upload files into the root-folder)
     */
    public function currentPageID()
    {
        if (is_numeric($this->getRequest()->requestVar('ID'))) {
            return $this->getRequest()->requestVar('ID');
        } elseif (array_key_exists('ID', $this->urlParams) && is_numeric($this->urlParams['ID'])) {
            return $this->urlParams['ID'];
        } elseif (Session::get("{$this->class}.currentPage")) {
            return Session::get("{$this->class}.currentPage");
        } else {
            return 0;
        }
    }

    /**
     * Gets the ID of the folder being requested.
     *
     * @return int
     */
    public function getCurrentFolderID()
    {
        $currentFolderID = 0;

        if ($this->urlParams['Action'] == 'show' && is_numeric($this->urlParams['ID'])) {
            $currentFolderID = $this->urlParams['ID'];
        }

        return $currentFolderID;
    }

    /**
     * Set up the controller
     */
    public function init()
    {
        parent::init();

        Requirements::javascript(FRAMEWORK_DIR . "/admin/client/dist/js/bundle-lib.js");
        Requirements::add_i18n_javascript(ASSET_ADMIN_DIR . '/client/lang', false, true);
        Requirements::css(ASSET_ADMIN_DIR . "/client/dist/styles/bundle.css");

        CMSBatchActionHandler::register('delete', 'SilverStripe\AssetAdmin\BatchAction\DeleteAssets', 'Folder');
    }

    /**
     * Returns the files and subfolders contained in the currently selected folder,
     * defaulting to the root node. Doubles as search results, if any search parameters
     * are set through {@link SearchForm()}.
     *
     * @return SS_List
     */
    public function getList()
    {
        $folder = $this->currentPage();
        $context = $this->getSearchContext();
        // Overwrite name filter to search both Name and Title attributes
        $context->removeFilterByName('Name');
        $params = $this->getRequest()->requestVar('q');
        $list = $context->getResults($params);

        // Don't filter list when a detail view is requested,
        // to avoid edge cases where the filtered list wouldn't contain the requested
        // record due to faulty session state (current folder not always encoded in URL, see #7408).
        if (!$folder->ID
            && $this->getRequest()->requestVar('ID') === null
            && ($this->getRequest()->param('ID') == 'field')
        ) {
            return $list;
        }

        // Re-add previously removed "Name" filter as combined filter
        // TODO Replace with composite SearchFilter once that API exists
        if (!empty($params['Name'])) {
            $list = $list->filterAny(array(
                'Name:PartialMatch' => $params['Name'],
                'Title:PartialMatch' => $params['Name']
            ));
        }

        // Always show folders at the top
        $list = $list->sort('(CASE WHEN "File"."ClassName" = \'Folder\' THEN 0 ELSE 1 END), "Name"');

        // If a search is conducted, check for the "current folder" limitation.
        // Otherwise limit by the current folder as denoted by the URL.
        if (empty($params) || !empty($params['CurrentFolderOnly'])) {
            $list = $list->filter('ParentID', $folder->ID);
        }

        // Category filter
        if (!empty($params['AppCategory'])
            && !empty(File::config()->app_categories[$params['AppCategory']])
        ) {
            $exts = File::config()->app_categories[$params['AppCategory']];
            $list = $list->filter('Name:PartialMatch', $exts);
        }

        // Date filter
        if (!empty($params['CreatedFrom'])) {
            $fromDate = new DateField(null, null, $params['CreatedFrom']);
            $list = $list->filter("Created:GreaterThanOrEqual", $fromDate->dataValue().' 00:00:00');
        }
        if (!empty($params['CreatedTo'])) {
            $toDate = new DateField(null, null, $params['CreatedTo']);
            $list = $list->filter("Created:LessThanOrEqual", $toDate->dataValue().' 23:59:59');
        }

        return $list;
    }

    /**
     * Get the search context
     *
     * @return SearchContext
     */
    public function getSearchContext()
    {
        $context = singleton('File')->getDefaultSearchContext();

        // Namespace fields, for easier detection if a search is present
        foreach ($context->getFields() as $field) {
            $field->setName(sprintf('q[%s]', $field->getName()));
        }
        foreach ($context->getFilters() as $filter) {
            $filter->setFullName(sprintf('q[%s]', $filter->getFullName()));
        }

        // Customize fields
        $dateHeader = HeaderField::create('q[Date]', _t('CMSSearch.FILTERDATEHEADING', 'Date'), 4);
        $dateFrom = DateField::create('q[CreatedFrom]', _t('CMSSearch.FILTERDATEFROM', 'From'))
        ->setConfig('showcalendar', true);
        $dateTo = DateField::create('q[CreatedTo]', _t('CMSSearch.FILTERDATETO', 'To'))
        ->setConfig('showcalendar', true);
        $dateGroup = FieldGroup::create(
            $dateHeader,
            $dateFrom,
            $dateTo
        );
        $context->addField($dateGroup);
        $appCategories = array(
            'archive' => _t('AssetAdmin.AppCategoryArchive', 'Archive', 'A collection of files'),
            'audio' => _t('AssetAdmin.AppCategoryAudio', 'Audio'),
            'document' => _t('AssetAdmin.AppCategoryDocument', 'Document'),
            'flash' => _t('AssetAdmin.AppCategoryFlash', 'Flash', 'The fileformat'),
            'image' => _t('AssetAdmin.AppCategoryImage', 'Image'),
            'video' => _t('AssetAdmin.AppCategoryVideo', 'Video'),
        );
        $context->addField(
            $typeDropdown = new DropdownField(
                'q[AppCategory]',
                _t('AssetAdmin.Filetype', 'File type'),
                $appCategories
            )
        );

        $typeDropdown->setEmptyString(' ');

        $context->addField(
            new CheckboxField('q[CurrentFolderOnly]', _t('AssetAdmin.CurrentFolderOnly', 'Limit to current folder?'))
        );
        $context->getFields()->removeByName('q[Title]');

        return $context;
    }

    /**
     * Returns a form for filtering of files and assets gridfield.
     * Result filtering takes place in {@link getList()}.
     *
     * @return Form
     *              @see AssetAdmin.js
     */
    public function searchForm()
    {
        $folder = $this->currentPage();
        $context = $this->getSearchContext();

        $fields = $context->getSearchFields();
        $actions = new FieldList(
            FormAction::create('doSearch', _t('CMSMain_left_ss.APPLY_FILTER', 'Apply Filter'))
                ->addExtraClass('ss-ui-action-constructive'),
            Object::create('ResetFormAction', 'clear', _t('CMSMain_left_ss.RESET', 'Reset'))
        );

        $form = new Form($this, 'filter', $fields, $actions);
        $form->setFormMethod('GET');
        $form->setFormAction(Controller::join_links($this->Link('show'), $folder->ID));
        $form->addExtraClass('cms-search-form');
        $form->loadDataFrom($this->getRequest()->getVars());
        $form->disableSecurityToken();
        // This have to match data-name attribute on the gridfield so that the javascript selectors work
        $form->setAttribute('data-gridfield', 'File');

        return $form;
    }

    public function addForm()
    {
        $form = CMSForm::create(
            $this,
            'AddForm',
            new FieldList(
                new TextField("Name", _t('File.Name')),
                new HiddenField('ParentID', false, $this->getRequest()->getVar('ParentID'))
            ),
            new FieldList(
                FormAction::create('doAdd', _t('AssetAdmin_left_ss.GO', 'Go'))
                    ->addExtraClass('ss-ui-action-constructive')->setAttribute('data-icon', 'accept')
                    ->setTitle(_t('AssetAdmin.ActionAdd', 'Add folder'))
            )
        )->setHTMLID('Form_AddForm');
        $form->setResponseNegotiator($this->getResponseNegotiator());
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        // TODO Can't merge $FormAttributes in template at the moment
        $form->addExtraClass('add-form cms-add-form cms-edit-form cms-panel-padded center ' . $this->BaseCSSClasses());

        return $form;
    }

    /**
     * Add a new group and return its details suitable for ajax.
     *
     * @todo Move logic into Folder class, and use LeftAndMain->doAdd() default implementation.
     */
    public function doAdd($data, $form)
    {
        $class = $this->stat('tree_class');

        // check create permissions
        if (!singleton($class)->canCreate()) {
            return Security::permissionFailure($this);
        }

        // check addchildren permissions
        if (singleton($class)->hasExtension('Hierarchy')
            && isset($data['ParentID'])
            && is_numeric($data['ParentID'])
            && $data['ParentID']
        ) {
            $parentRecord = DataObject::get_by_id($class, $data['ParentID']);
            if ($parentRecord->hasMethod('canAddChildren') && !$parentRecord->canAddChildren()) {
                return Security::permissionFailure($this);
            }
        } else {
            $parentRecord = null;
        }

        // Check parent
        $parentID = $parentRecord && $parentRecord->ID
            ? (int) $parentRecord->ID
            : 0;
        // Build filename
        $filename = isset($data['Name'])
            ? basename($data['Name'])
            : _t('AssetAdmin.NEWFOLDER', "NewFolder");
        if ($parentRecord && $parentRecord->ID) {
            $filename = $parentRecord->getFilename() . '/' . $filename;
        }

        // Get the folder to be created

        // Ensure name is unique
        foreach ($this->getNameGenerator($filename) as $filename) {
            if (! File::find($filename)) {
                break;
            }
        }

        // Create record
        $record = Folder::create();
        $record->ParentID = $parentID;
        $record->Name = $record->Title = basename($filename);
        $record->write();

        if ($parentRecord) {
            return $this->redirect(Controller::join_links($this->Link('show'), $parentRecord->ID));
        } else {
            return $this->redirect($this->Link());
        }
    }

    /**
     * Get an asset renamer for the given filename.
     *
     * @param  string             $filename Path name
     * @return AssetNameGenerator
     */
    protected function getNameGenerator($filename)
    {
        return Injector::inst()
            ->createWithArgs('AssetNameGenerator', array($filename));
    }

    /**
     * Custom currentPage() method to handle opening the 'root' folder
     */
    public function currentPage()
    {
        $id = $this->currentPageID();
        if ($id && is_numeric($id) && $id > 0) {
            $folder = DataObject::get_by_id('Folder', $id);
            if ($folder && $folder->exists()) {
                return $folder;
            }
        }
        $this->setCurrentPageID(null);

        return new Folder();
    }

     * @todo Implement on client
     */
    public function breadcrumbs($unlinked = false)
    {
        return null;
    }


    /**
     * Don't include class namespace in auto-generated CSS class
     */
    public function baseCSSClasses()
    {
        return 'AssetAdmin LeftAndMain';
    }

    /**
     * Don't include class namespace in template names
     * @todo Make code in framework more namespace-savvy so that we don't need this duplication
     */
    public function getTemplatesWithSuffix($suffix)
    {
        $className = get_class($this);
        $baseClass = 'LeftandMain';

        $templates = array();
        $classes = array_reverse(\ClassInfo::ancestry($className));
        foreach ($classes as $class) {
            $template = (new \ReflectionClass($class))->getShortName() . $suffix;
            if (\SSViewer::hasTemplate($template)) {
                $templates[] = $template;
            }

            // If the class is "Page_Controller", look for Page.ss
            if (stripos($class, '_controller') !== false) {
                $template = str_ireplace('_controller', '', $class) . $suffix;
                if (\SSViewer::hasTemplate($template)) {
                    $templates[] = $template;
                }
            }

            if ($baseClass && $class == $baseClass) {
                break;
            }
        }

        return $templates;
    }

    public function providePermissions()
    {
        $title = _t("AssetAdmin.MENUTITLE", LeftAndMain::menu_title_for_class($this->class));

        return array(
            "CMS_ACCESS_AssetAdmin" => array(
                'name' => _t('CMSMain.ACCESS', "Access to '{title}' section", array('title' => $title)),
                'category' => _t('Permission.CMS_ACCESS_CATEGORY', 'CMS Access')
            )
        );
    }
}
