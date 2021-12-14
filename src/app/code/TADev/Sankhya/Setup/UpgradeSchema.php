<?php

/**
* TA Dev
*
* NOTICE OF LICENSE
* @author TA Dev Core Team <suporte@tatecnologia.com>
*/

namespace TADev\Sankhya\Setup;

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
            if (!$installer->tableExists('tadev_sankhya_orders')) {
                $table = $installer->getConnection()->newTable(
                    $installer->getTable('tadev_sankhya_orders')
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
                        'sankhya_order_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        36,
                        [
                            'nullable' => false
                        ],
                        'Sankhya Order ID'
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
                        'Sankhya Sales Order Code'
                    )
                    ->addColumn(
                        'events',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        \Magento\Framework\DB\Ddl\Table::DEFAULT_TEXT_SIZE,
                        [],
                        'Sankhya Order Events'
                    );
                $installer->getConnection()->createTable($table);

                $installer->getConnection()->addIndex(
                    $installer->getTable('tadev_sankhya_orders'),
                    $setup->getIdxName(
                        $installer->getTable('tadev_sankhya_orders'),
                        ['sankhya_order_id', 'sales_order_code', 'events'],
                        \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT
                    ),
                    ['sankhya_order_id', 'sales_order_code', 'events'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT
                );
            }
        }

        $installer->endSetup();
    }
}
