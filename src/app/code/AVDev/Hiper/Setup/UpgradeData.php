<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeData implements UpgradeDataInterface
{
    const SUFIX_ATTRIBUTES = '_hiper';

    /**
     * Eav setup factory
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * Init
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
    )
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '1.1.0', '<')) {
            $eavSetup = $this->eavSetupFactory->create();
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'height' . static::SUFIX_ATTRIBUTES,
                [
                    'group' => 'Hiper',
                    'type' => 'decimal',
                    'label' => 'Height',
                    'input' => 'text',
                    'backend' => 'AVDev\Hiper\Model\Attribute\Backend\Height',
                    'required' => false,
                    'sort_order' => 10,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                    'visible' => true,
                    'is_html_allowed_on_front' => true,
                    'visible_on_front' => true,
                    'filterable_in_search' => true
                ]
            );

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'width' . static::SUFIX_ATTRIBUTES,
                [
                    'group' => 'Hiper',
                    'type' => 'decimal',
                    'label' => 'Width',
                    'input' => 'text',
                    'backend' => 'AVDev\Hiper\Model\Attribute\Backend\Width',
                    'required' => false,
                    'sort_order' => 20,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                    'visible' => true,
                    'is_html_allowed_on_front' => true,
                    'visible_on_front' => true,
                    'filterable_in_search' => true
                ]
            );

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'length' . static::SUFIX_ATTRIBUTES,
                [
                    'group' => 'Hiper',
                    'type' => 'decimal',
                    'label' => 'Length',
                    'input' => 'text',
                    'backend' => 'AVDev\Hiper\Model\Attribute\Backend\Length',
                    'required' => false,
                    'sort_order' => 30,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                    'visible' => true,
                    'is_html_allowed_on_front' => true,
                    'visible_on_front' => true,
                    'filterable_in_search' => true
                ]
            );

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'brand' . static::SUFIX_ATTRIBUTES,
                [
                    'group' => 'Hiper',
                    'type' => 'varchar',
                    'label' => 'Brand',
                    'input' => 'text',
                    'frontend_class' => 'validate-length maximum-length-60',
                    'required' => false,
                    'sort_order' => 40,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                    'visible' => true,
                    'is_html_allowed_on_front' => true,
                    'visible_on_front' => true,
                    'filterable_in_search' => true
                ]
            );

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'unity' . static::SUFIX_ATTRIBUTES,
                [
                    'group' => 'Hiper',
                    'type' => 'varchar',
                    'label' => 'Unity',
                    'input' => 'text',
                    'frontend_class' => 'validate-length maximum-length-3',
                    'required' => false,
                    'sort_order' => 40,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                    'visible' => true,
                    'is_html_allowed_on_front' => true,
                    'visible_on_front' => true,
                    'filterable_in_search' => true
                ]
            );

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'id' . static::SUFIX_ATTRIBUTES,
                [
                    'group' => 'Hiper',
                    'type' => 'varchar',
                    'label' => 'Hiper ID',
                    'input' => 'text',
                    'frontend_class' => 'validate-length maximum-length-36',
                    'required' => false,
                    'sort_order' => 40,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                    'visible' => false,
                    'is_html_allowed_on_front' => false,
                    'visible_on_front' => false,
                    'filterable_in_search' => false
                ]
            );
        }
    }
}
