<?php

namespace OuterEdge\BulkActions\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use Magento\Framework\App\DeploymentConfig;

class MassOrderStatus extends \Magento\Backend\App\Action
{
    public function __construct(
        Context $context,
        protected OrderRepositoryInterface $orderRepository,
        protected LoggerInterface $logger,
        protected ResourceConnection $resourceConnection,
        protected DeploymentConfig $deploymentConfig
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $ordersId = $this->getRequest()->getParam('selected');

        if (!empty($ordersId)) {
            foreach ($ordersId as $orderId) {

                $order = $this->orderRepository->get($orderId);
                $incrementId = $order->getIncrementId();

                $connection = $this->resourceConnection->getConnection();

                $tablePrefix = $this->deploymentConfig->get('db/table_prefix');
                $tableSalesOrder = $tablePrefix.$connection->getTableName('sales_order');
                $tableSalesOrderGrid = $tablePrefix.$connection->getTableName('sales_order_grid');

                try {
                    $connection->fetchRow(
                        "UPDATE $tableSalesOrder SET `state` = '".Order::STATE_COMPLETE."', `status` = '".Order::STATE_COMPLETE."' WHERE `entity_id` = $orderId");

                    $connection->fetchRow(
                        "UPDATE $tableSalesOrderGrid SET`status` = '".Order::STATE_COMPLETE."' WHERE `increment_id` = $incrementId");

                } catch (\Exception $e) {
                    $this->logger->error($e);
                    $this->messageManager->addExceptionMessage($e, $e->getMessage());
                }
            }
        }
        return $resultRedirect->setPath('sales/order/index', [], ['error' => true]);
    }
}
