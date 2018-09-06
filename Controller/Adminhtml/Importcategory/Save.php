<?php
/**
* Navin Bhudiya
* Copyright (C) 2016 Navin Bhudiya <navindbhudiya@gmail.com>
*
* NOTICE OF LICENSE
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program. If not, see http://opensource.org/licenses/gpl-3.0.html.
*
* @category Navin
* @package Navin_ImportExportCategory
* @copyright Copyright (c) 2016 Mage Delight (http://www.navinbhudiya.com/)
* @license http://opensource.org/licenses/gpl-3.0.html GNU General Public License,version 3 (GPL-3.0)
* @author Navin Bhudiya <navindbhudiya@gmail.com>
*/
namespace Navin\ImportExportCategory\Controller\Adminhtml\Importcategory;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Registry;
use Magento\Backend\App\Action\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Backend\Model\Session;
use Magento\Backend\App\Action;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Save extends Action
{
    /**
     * Backend session
     *
     * @var Session
     */
    protected $backendSession;

    /**
     * Save constructor.
     * @param Registry $registry
     * @param UploaderFactory $fileUploaderFactory
     * @param Filesystem $fileSystem
     * @param Reader $moduleReader
     * @param Csv $fileCsv
     * @param StoreManagerInterface $storeManagerInterface
     * @param CategoryFactory $categoryFactory
     * @param LoggerInterface $logger
     * @param File $fileio
     * @param Context $context
     */
    public function __construct(
        Registry $registry,
        UploaderFactory $fileUploaderFactory,
        Filesystem $fileSystem,
        Reader $moduleReader,
        Csv $fileCsv,
        StoreManagerInterface $storeManagerInterface,
        CategoryFactory $categoryFactory,
        LoggerInterface $logger,
        File $fileio,
        Context $context
    ) {
        $this->backendSession = $context->getSession();
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->filesystem = $fileSystem;
        $this->moduleReader = $moduleReader;
        $this->fileCsv = $fileCsv;
        $this->storeManager = $storeManagerInterface;
        $this->categoryFactory = $categoryFactory;
        $this->registry = $registry;
        $this->logger = $logger;
        $this->fileio = $fileio;
        parent::__construct($context);
    }

    /**
     * @uses given CSV file to import categories
     *
     * @return Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $this->registry->register('isSecureArea', true);
        
        try {
            $filePath = $this->uploadFileAndGetName();
            
            if (($filePath != '') && file_exists($filePath)) {
                chmod($filePath, 0777);
                $data = $this->fileCsv->getData($filePath);
                
                /* If the file is not empty, proceed with importing */
                if (isset($data[0]) && !empty($data[0])) {
                    
                    $header = $data[0];
                    $categoriesKey = array_search('categories', $header);
                    $categoryIdKey = array_search('category_id', $header);
                    $storeData = array_search('store', $header);

                    $store = $this->storeManager->getStore();
                    $storeId = $store->getStoreId();
                    $singleStoreMode = $this->storeManager->isSingleStoreMode();
                    $storeArray = [];
                    
                    if (!$singleStoreMode) {
                        $stores = $this->storeManager->getStores();
                        
                        foreach ($stores as $key => $store) {
                            $storeArray[$store->getCode()] = $store->getId();
                        }
                    }
                    
                    $rootNodeId = $store->getRootCategoryId();
                    $rootCat = $this->categoryFactory->create();
                    $categoryInfo = $rootCat->load($rootNodeId);

                    $alreadyExist = [];
                    $categoryCollection = $this->categoryFactory
                        ->create()
                        ->getCollection();
                    $existingCatPath = [];
                    $existingCatPathName = [];
                        
                    foreach ($categoryCollection as $key => $value) {
                        $existingCategories[$value->getId()] = $value->getName();
                        $existingCatPath[$value->getId()] = $value->getPath();
                        $checkCat = $this->categoryFactory->create();
                        $categoryObj = $checkCat->load($value->getId());
                        $parentCatNames = [];
                        $parentId = '';
                        
                        foreach ($this->getParentCategories($categoryObj) as $key => $parentCat) {
                            $parentCatNames[] = $parentCat->getName();
                            $parentId = $parentCat->getId();
                        }
                        
                        $parentCategories = implode('/', $parentCatNames);
                        
                        if ($parentCategories && $parentId) {
                            $existingCatPathName[$parentId] = $parentCategories;
                        }
                    }

                    foreach ($data as $key => $categoryItem) {
                        if ($key != 0) {
                            $catData = $this->getKeyValue($categoryItem, $header);

                            if (isset($catData['category_id'])) {
                                unset($catData['category_id']);
                            }

                            if (isset($categoriesKey) && ($categoriesKey != '' || $categoriesKey === 0)) {
                                $arrayKey = array_search($categoryItem[$categoriesKey], $existingCatPathName);

                                if ($arrayKey) {
                                    $alreadyExist[] = $categoryItem[$categoriesKey];
                                } else {
                                    $strMark = strrpos($categoryItem[$categoriesKey], '/');
                                    $categoryId = '';
                                    $newCategory = '';

                                    if ($strMark != false) {
                                        $parentPath = substr($categoryItem[$categoriesKey], 0, $strMark);
                                        $newCategory = substr($categoryItem[$categoriesKey], $strMark + 1);
                                        $categoryId = array_search($parentPath, $existingCatPathName);
                                    } else {
                                        $newCategory = $categoryItem[$categoriesKey];
                                        $categoryId = $categoryInfo->getId();
                                    }

                                    if ($categoryId != '' && $newCategory != '') {
                                        $cateItem = $this->categoryFactory->create();
                                        $cateItem->setData($catData);
                                        $parentCategory = $this->categoryFactory->create();
                                        $parentCategory->load($categoryId);

                                        if ($parentCategory->getId()) {
                                            $cateItem->setParentId($categoryId);
                                            $cateItem->setPath($parentCategory->getPath());
                                        }

                                        $cateItem->setAttributeSetId($cateItem->getDefaultAttributeSetId());
                                        $cateItem->setName($newCategory);

                                        // if url_key is specified use that key, otherwise use generated key
                                        if ($catData['url_key']) {
                                            $urlKey = $catData['url_key'];
                                        } else {
                                            $urlKey = str_replace(' ', '-', strtolower($newCategory));
                                        }

                                        if (in_array($newCategory, $existingCategories)) {
                                            $urlKey .= '-'.mt_rand(10, 99);
                                        }
                                        $cateItem->setUrlKey($urlKey);
                                        $cateItem->setStoreId($storeId);
                                        $cateItem->save();
                                        
                                        if ($cateItem->getId()) {
                                            $existingCategories[$cateItem->getId()] = $cateItem->getName();
                                            $existingCatPath[$cateItem->getId()] = $cateItem->getPath();
                                            $existingCatPathName[$cateItem->getId()] = $categoryItem[$categoriesKey];
                                        }
                                    }
                                }
                            } elseif (isset($categoryIdKey) &&
                                        ($categoryIdKey != '' || $categoryIdKey === 0) && $storeData != '') {
                                //update categories
                                $cateModel = $this->categoryFactory->create();

                                if (!$singleStoreMode && isset($storeArray[$categoryItem[$storeData]])) {
                                    $cateModel->setStoreId($storeArray[$categoryItem[$storeData]]);
                                } else {
                                    $cateModel->setStoreId($storeId);
                                }
                                $cateItem = $cateModel->load($categoryItem[$categoryIdKey]);
                                $noCategoryFound = true;

                                if ($cateItem->getId()) {
                                    $noCategoryFound = false;
                                    $attributeSetId = $cateItem->getAttributeSetId();
                                    $categoryId = $cateItem->getParentId();

                                    foreach ($catData as $key => $value) {
                                        $acceptedKeys = array(
                                            'url_key',
                                            'category_id',
                                            'url_path',
                                            'path',
                                            'level',
                                            'children_count',
                                            'full_path');

                                        if (!in_array($key, $acceptedKeys)) {
                                            $cateItem->setData($key, $value);
                                        }
                                    }
                                    $parentId = $cateItem->getParentId();

                                    if ($parentId != $categoryId && $cateItem->getId() > 2) {
                                        $categoryModel = $this->categoryFactory->create();
                                        $parentCategories = $categoryModel->load($parentId);

                                        if ($parentCategories->getId()) {
                                            $cateItem->setPath($parentCategories->getPath() . '/'
                                                . $cateItem->getId());
                                        } else {
                                            $this->messageManager->addError('Parent category not Found.');
                                            $resultRedirect->setPath('navin_importexportcategory/*/edit');
                                            return $resultRedirect;
                                        }
                                        
                                        $cateItem->move($parentId, false);
                                    }

                                    if ($cateItem->getId() <= 2) {
                                        $cateItem->unsetData('posted_products');
                                    }
                                    
                                    $cateItem->save();
                                }
                            } else {
                                $this->messageManager->addError('Data Column not Found.');
                                $resultRedirect->setPath('navin_importexportcategory/*/edit');
                                return $resultRedirect;
                            }
                        }
                    }
                    
                    if (isset($alreadyExist) && !empty($alreadyExist)) {
                        $this->messageManager->addError(
                            __(sprintf('These categories already exist: %s', implode(', ', $alreadyExist)))
                        );
                        $this->messageManager->addSuccess(__('Other categories have been imported Successfully'));
                    } elseif (isset($categoryIdKey) && $categoryIdKey === 0) {
                        if ($noCategoryFound) {
                            $this->messageManager->addError(__('No Category Found.'));
                        } else {
                            $this->messageManager->addSuccess(__('Categories have been updated Successfully'));
                        }
                    } else {
                        $this->messageManager->addSuccess(__('Categories have been imported Successfully'));
                    }
                        unlink($filePath);
                        $this->backendSession->setNavinImportcategoryTestData(false);
                        $resultRedirect->setPath('navin_importexportcategory/*/edit');
                        return $resultRedirect;
                } else {
                    $this->messageManager->addError('Data Not Found.');
                    $resultRedirect->setPath('navin_importexportcategory/*/edit');
                    return $resultRedirect;
                }
            } else {
                $this->messageManager->addError('File not Found.');
                $resultRedirect->setPath('navin_importexportcategory/*/edit');
                return $resultRedirect;
            }
        } catch (LocalizedException $e) {
            $this->logger->debug($e->getMessage());
            $this->messageManager->addError($e->getMessage());
        } catch (RuntimeException $e) {
            $this->logger->debug($e->getMessage());
            $this->messageManager->addError($e->getMessage());
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $this->messageManager->addException($e, __('Something went wrong while saving the category.'));
        }
        $resultRedirect->setPath(
            'navin_importexportcategory/*/edit',
            [
                '_current' => true
            ]
        );
        return $resultRedirect;
    }

    /**
     * Validates valid CSV file and uploads to file directory
     *
     * @return bool|string
     * @throws \Exception
     */
    protected function uploadFileAndGetName()
    {
        $uploader = $this->fileUploaderFactory->create(['fileId' => 'file']);
        $uploader->setAllowedExtensions(['CSV', 'csv']);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        $path = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)
        ->getAbsolutePath('categoryimport');

        if (!is_dir($path)) {
            $this->fileio->mkdir($path, '0777', true);
            $this->fileio->chmod($path, '0777', true);
        }
        $result = $uploader->save($path.'/');
        if (isset($result['file']) && !empty($result['file'])) {
            return $result['path'].$result['file'];
        }
        return false;
    }

    /**
     * @param $row
     * @param $headerArray
     * @return array
     */
    protected function getKeyValue($row, $headerArray)
    {
        $temp = [];
        foreach ($headerArray as $key => $value) {
            if ($value == 'image') {
                $temp[$value] = $this->getImagePath($row[$key]);
            } elseif ($value == 'products' && $row[$key]!='') {
                $temp['posted_products'] = array_flip(explode('|', $row[$key]));
            } else {
                $temp[$value] = $row[$key];
            }
        }
        return $temp;
    }

    /**
     * Fetches image from URL and inserts it into the image path directory
     *
     * @param $categoryImage
     * @return mixed
     */
    protected function getImagePath($categoryImage)
    {
        $webUrl = strpos($categoryImage, 'http://');

        if ($webUrl !== false) {
            $imagePath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)
                ->getAbsolutePath('catalog/category');
            $this->fileio->mkdir($imagePath, '0777', true);
            $file = file_get_contents($categoryImage);

            if ($file != '') {
                $allowed =  ['gif','png' ,'jpg', 'jpeg'];
                $ext = strtolower(pathinfo($categoryImage, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    $imageName = pathinfo($categoryImage, PATHINFO_BASENAME);

                    if (!is_dir($imagePath)) {
                        $this->fileio->mkdir($imagePath, '0777', true);
                        $this->fileio->chmod($imagePath, '0777', true);
                    }
                    $imagePath = $imagePath.'/'.$imageName;
                    $result = file_put_contents($imagePath, $file);

                    if ($result) {
                        return $imageName;
                    }
                }
            }
        } else {
            return $categoryImage;
        }
    }

    /**
     * @param $category
     * @return DataObject[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getParentCategories($category)
    {
        $pathIds = array_reverse(explode(',', $category->getPathInStore()));
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $categories */
        $categories = $this->categoryFactory->create()->getCollection();
        return $categories->setStore(
            $this->storeManager->getStore()
        )->addAttributeToSelect(
            'name'
        )->addAttributeToSelect(
            'url_key'
        )->addFieldToFilter(
            'entity_id',
            ['in' => $pathIds]
        )->load()->getItems();
    }
}
