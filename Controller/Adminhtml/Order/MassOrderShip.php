<?php

namespace OuterEdge\BulkActions\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Sales\Model\OrderFactory as ModelOrder;
use Magento\Sales\Model\Convert\OrderFactory as ConvertOrder;
use Magento\Sales\Model\Convert\Order;
use Magento\Framework\Message\ManagerInterface;
use Magento\Shipping\Model\ShipmentNotifier;
use Magento\Framework\DB\TransactionFactory;

class MassOrderShip extends \Magento\Backend\App\Action
{
    /**
     * @var ModelOrder
     */
    protected $orderModel;

    /**
     * @var Order
     */
    protected $convertOrder;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var ShipmentNotifier
     */
    protected $shipmentNotifier;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * Preparation constructor.
     * @param Context $context
     * @param ManagerInterface $messageManager
     * @param ModelOrder $orderModel
     * @param ConvertOrder $convertOrder
     * @param ShipmentNotifier $shipmentNotifier
     * @param TransactionFactory $transactionFactory
     */
    public function __construct(
        Context $context,
        ManagerInterface $messageManager,
        ModelOrder $orderModel,
        ConvertOrder $convertOrder,
        ShipmentNotifier $shipmentNotifier,
        TransactionFactory $transactionFactory
    ) {
        parent::__construct($context);

        $this->messageManager = $messageManager;
        $this->orderModel = $orderModel;
        $this->convertOrder = $convertOrder;
        $this->shipmentNotifier = $shipmentNotifier;
        $this->transactionFactory = $transactionFactory;
    }

    public function execute()
    {
        /* @var $saveTransaction \Magento\Framework\DB\Transaction */
        $saveTransaction = $this->transactionFactory->create();
        $resultRedirect = $this->resultRedirectFactory->create();
        $shipmentArray = [];

        $ordersId = $this->getRequest()->getParam('selected');
        $notify = $this->getRequest()->getParam('notify');

        if (!empty($ordersId)) {
            // Ship Order
            foreach ($ordersId as $orderId) {

                $order = $this->orderModel->create()->load($orderId);
                if ($order->canShip()) {
                    $convertOrder = $this->convertOrder->create();
                    $shipment = $convertOrder->toShipment($order);

                    foreach ($order->getAllItems() AS $orderItem) {
                        // Check if order item has qty to ship or is virtual
                        if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                            continue;
                        }
                        $qtyShipped = $orderItem->getQtyToShip();
                        // Create shipment item with qty
                        $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);
                        // Add shipment item to shipment
                        $shipment->addItem($shipmentItem);
                    }

                    // Register shipment
                    $shipment->register();
                    $shipment->getOrder()->setIsInProcess(true);

                    //Add shipment to transaction
                    $saveTransaction->addObject($shipment);
                    $saveTransaction->addObject($shipment->getOrder());
                    $shipmentArray[$order->getIncrementId()] = $shipment;

                } else {
                    $this->messageManager->addError(__("Cannot ship order".$order->getIncrementId().". It's already created or something went wrong"));
                }
            }

            if (!empty($shipmentArray)) {
                try {
                    // Save created shipment
                    $saveTransaction->save();

                    // Send email
                    if ($notify) {
                        foreach ($shipmentArray as $shipment) {
                            $this->shipmentNotifier->notify($shipment);
                        }
                    }
                    $ordersIncrementIds = implode(",", array_keys($shipmentArray));
                    $this->messageManager->addSuccess(__("Shipment Succesfully Generated for orders: ".$ordersIncrementIds));
                } catch (\Exception $e) {
                    $this->messageManager->addError(__('Cannot ship order'. $e->getMessage()));
                }
            }
        }
        return $resultRedirect->setPath('sales/order/index', [], ['error' => true]);
    }
}
