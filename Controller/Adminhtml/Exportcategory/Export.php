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
namespace Navin\ImportExportCategory\Controller\Adminhtml\Exportcategory;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Raw;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Backend\App\Action\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\App\Response\Http\FileFactory;

class Export extends Action
{
    /**
     * Redirect result factory
     *
     * @var ForwardFactory
     */
    protected $resultForwardFactory;

    /**
     * Export constructor.
     * @param ForwardFactory $resultForwardFactory
     * @param StoreManagerInterface $storeManagerInterface
     * @param CategoryFactory $categoryFactory
     * @param Collection $prodcollection
     * @param RawFactory $resultRawFactory
     * @param FileFactory $fileFactory
     * @param Context $context
     */
    public function __construct(
        ForwardFactory $resultForwardFactory,
        StoreManagerInterface $storeManagerInterface,
        CategoryFactory $categoryFactory,
        Collection $prodcollection,
        RawFactory $resultRawFactory,
        FileFactory $fileFactory,
        Context $context
    ) {
        $this->resultForwardFactory = $resultForwardFactory;
        $this->storeManager = $storeManagerInterface;
        $this->categoryFactory = $categoryFactory;
        $this->productcollection = $prodcollection;
        $this->resultRawFactory = $resultRawFactory;
        $this->fileFactory = $fileFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $storeId = $this->getRequest()->getPost('store_id');
        $singleStoreMode = $this->storeManager->isSingleStoreMode();
        $storeArray = [];
        if (!$singleStoreMode) {
            $stores = $this->storeManager->getStores();
            foreach ($stores as $key => $store) {
                $storeArray[$store->getId()] = $store->getCode();
            }
        }

        $fileName = 'categories.csv';

        //set the headers for the CSV export file
        $contentHeaders = array(
            "category_id",
            "parent_id",
            "store",
            "name",
            "path",
            "image",
            "url_key",
            "is_active",
            "is_anchor",
            "meta_description",
            "display_mode",
            "custom_use_parent_settings",
            "custom_apply_to_products",
            "include_in_menu",
            "meta_title",
            "meta_keywords",
            "custom_design",
            "custom_design_from",
            "custom_design_to",
            "default_sort_by",
            "page_layout",
            "description",
            "products"
        );

        $content = implode(",", $contentHeaders) . "\n";

        $collection = $this->categoryFactory->create()->getCollection()->addAttributeToSort('entity_id', 'asc');

        foreach ($collection as $key => $cat) {
            $categoryItem = $this->categoryFactory->create();

            if ($cat->getId() >= 2) {
                $categoryItem->setStoreId($storeId);
                $categoryItem->load($cat->getId());

                if ($categoryItem->getId()) {
                    $prodIds = '';
                    $productIds = $this->productcollection->addCategoryFilter($categoryItem)->getAllIds();

                    if (isset($productIds) && !empty($productIds)) {
                        $prodIds = $productIds = implode('|', $productIds);
                    }
                    //storing values into CVS file
                    $categoryRow = array(
                        $categoryItem->getId(),
                        $categoryItem->getParentId(),
                        $storeArray[$categoryItem->getStoreId()],
                        $categoryItem->getName(),
                        $categoryItem->getPath(),
                        $categoryItem->getImage(),
                        $categoryItem->getUrlKey(),
                        $categoryItem->getIsActive(),
                        $categoryItem->getIsAnchor(),
                        $categoryItem->getIncludeInMenu(),
                        $categoryItem->getMetaTitle(),
                        $categoryItem->getMetaKeywords(),
                        $categoryItem->getMetaDescription(),
                        $categoryItem->getDisplayMode(),
                        $categoryItem->getCustomUseParentSettings(),
                        $categoryItem->getCustomApplyToProducts(),
                        $categoryItem->getCustomDesign(),
                        $categoryItem->getCustomDesignFrom(),
                        $categoryItem->getCustomDesignTo(),
                        $categoryItem->getDefaultSortBy(),
                        $categoryItem->getDescription(),
                        $prodIds
                    );
                    $content .= '"' . implode('","', $categoryRow) . '"' . "\n";
                }
            }
        }
        $this->prepareDownloadResponse($fileName, $content);
    }

    /**
     * @param $name
     * @param $content
     * @return Raw
     * @throws \Exception
     */
    public function prepareDownloadResponse($name, $content)
    {
        $fileName = $name;
        $this->fileFactory->create(
            $fileName,
            $content,
            'var',
            'text/csv',
            strlen($content)
        );
        $resultRaw = $this->resultRawFactory->create();
        return $resultRaw;
    }
}
