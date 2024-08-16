<?php

namespace OuterEdge\BulkActions\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;

class MassOrderStatus extends \Magento\Backend\App\Action
{
    public function __construct(
        Context $context,
        protected LoggerInterface $logger,
        protected ResourceConnection $resourceConnection,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $ordersId       = $this->getRequest()->getParam('selected');

        if (!empty($ordersId)) {
            foreach ($ordersId as $orderId) {
                $tableSalesOrder     = $this->resourceConnection->getTableName('sales_order');
                $tableSalesOrderGrid = $this->resourceConnection->getTableName('sales_order_grid');

                try {
                    $connection = $this->resourceConnection->getConnection();
                    $connection->fetchRow(
                        "UPDATE $tableSalesOrder SET `state` = '".Order::STATE_COMPLETE."', `status` = '".Order::STATE_COMPLETE."' WHERE `entity_id` = $orderId");
                    $connection->fetchRow(
                        "UPDATE $tableSalesOrderGrid SET`status` = '".Order::STATE_COMPLETE."' WHERE `entity_id` = $orderId");

                } catch (\Exception $e) {
                    $this->logger->error($e);
                    $this->messageManager->addExceptionMessage($e, $e->getMessage());
                }
            }
        }
        
        return $resultRedirect->setPath('sales/order/index', [], ['error' => true]);
    }
}
