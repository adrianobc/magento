<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.2.0', '<')) {
            if (!$installer->tableExists('avdev_hiper_orders')) {
                $table = $installer->getConnection()->newTable(
                    $installer->getTable('avdev_hiper_orders')
                )
                    ->addColumn(
                        'id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        10,
                        [
                            'identity' => true,
                            'nullable' => false,
                            'primary' => true,
                            'unsigned' => true
                        ]
                    )
                    ->addColumn(
                        'increment_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        32,
                        [],
                        'Order Increment ID'
                    )
                    ->addColumn(
                        'hiper_order_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        36,
                        [
                            'nullable' => false
                        ],
                        'Hiper Order ID'
                    )
                    ->addColumn(
                        'processing_status_code',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        2,
                        [],
                        'Processing 1 // Successfully processed 2 // Processed with error 3'
                    )
                    ->addColumn(
                        'sales_order_code',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        40,
                        [],
                        'Hiper Sales Order Code'
                    )
                    ->addColumn(
                        'events',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        \Magento\Framework\DB\Ddl\Table::DEFAULT_TEXT_SIZE,
                        [],
                        'Hiper Order Events'
                    );
                $installer->getConnection()->createTable($table);

                $installer->getConnection()->addIndex(
                    $installer->getTable('avdev_hiper_orders'),
                    $setup->getIdxName(
                        $installer->getTable('avdev_hiper_orders'),
                        ['hiper_order_id', 'sales_order_code', 'events'],
                        \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT
                    ),
                    ['hiper_order_id', 'sales_order_code', 'events'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT
                );
            }
        }

        $installer->endSetup();
    }
}
