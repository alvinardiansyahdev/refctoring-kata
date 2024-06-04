<?php

declare(strict_types=1);

namespace Supermarket\Model;

use Ds\Map;

class ShoppingCart
{
    private const MINIMUM_QUANTITY_FOR_OFFERING = 2;
    /**
     * @var ProductQuantity[]
     */
    private array $items = [];

    /**
     * @var Map<Product, float>
     */
    private Map $productQuantities;

    public function __construct()
    {
        $this->productQuantities = new Map();
    }

    public function addItem(Product $product): void
    {
        $this->addItemQuantity($product, 1.0);
    }

    /**
     * @return ProductQuantity[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function addItemQuantity(Product $product, float $quantity): void
    {
        $this->items[] = new ProductQuantity($product, $quantity);
        if ($this->productQuantities->hasKey($product)) {
            $newAmount = $this->productQuantities[$product] + $quantity;
            $this->productQuantities[$product] = $newAmount;
        } else {
            $this->productQuantities[$product] = $quantity;
        }
    }

    /**
     * @param Map<Product, Offer> $offers
     */
    public function handleOffers(Receipt $receipt, Map $offers, SupermarketCatalog $catalog): void
    {
        /**
         * @var Product $p
         * @var float $quantity
         */
        foreach ($this->productQuantities as $product => $quantity) {
            $quantityAsInt = (int) $quantity;
            if (!$offers->hasKey($product)) {
                continue;
            }

            /** @var Offer $offer */
            $offer = $offers[$product];
            $unitPrice = $catalog->getUnitPrice($product);
            $discount = null;
            $multiplier = 1;

            if ($this->threeForTwo($offer)) {
                $multiplier = 3;
            } else if ($this->twoForAmount($offer)) {
                $multiplier = 2;
            } else if ($this->fiveForAmount($offer)) {
                $multiplier = 5;
            }

            if ($this->twoForAmount($offer) && $this->isSatisfyTwoForAmountOffering($quantityAsInt)) {
                $total = $offer->getArgument() * intdiv($quantityAsInt, $multiplier) + $quantityAsInt % 2 * $unitPrice;
                $discountAmount = $unitPrice * $quantity - $total;
                $discount = new Discount(
                    product: $product,
                    description: "${multiplier} for {$offer->getArgument()}",
                    discount: -$discountAmount
                );
            }

            $multipliedQuantity = intdiv($quantityAsInt, $multiplier);
            if ($this->threeForTwo($offer) && $this->isSatisfyThreeForTwoOffering($quantityAsInt)) {
                $total = $multipliedQuantity * 2 * $unitPrice + $quantityAsInt % 3 * $unitPrice;
                $discountAmount = $quantity * $unitPrice - $total;
                $discount = new Discount(
                    product: $product,
                    description: "${multiplier} for 2",
                    discount: -$discountAmount
                );
            }

            if ($this->tenPercentDiscount($offer)) {
                $discount = new Discount(
                    product: $product,
                    description: "{$offer->getArgument()}% off",
                    discount: -$quantity * $unitPrice * $offer->getArgument() / 100.0
                );
            }
            if ($this->fiveForAmount($offer) && $this->isSatisfyFiveForAmountOffering($quantityAsInt)) {
                $discountTotal = $unitPrice * $quantity - ($offer->getArgument() * $multipliedQuantity + $quantityAsInt % 5 * $unitPrice);
                $discount = new Discount(
                    product: $product,
                    description: "${multiplier} for {$offer->getArgument()}",
                    discount: -$discountTotal
                );
            }

            if (!empty($discount)) {
                $receipt->addDiscount($discount);
            }
        }
    }

    private function threeForTwo($offer): bool
    {
        return $offer->getOfferType()->equals(SpecialOfferType::THREE_FOR_TWO());
    }

    private function twoForAmount($offer): bool
    {
        return $offer->getOfferType()->equals(SpecialOfferType::TWO_FOR_AMOUNT());
    }

    private function fiveForAmount($offer): bool
    {
        return $offer->getOfferType()->equals(SpecialOfferType::FIVE_FOR_AMOUNT());
    }

    private function tenPercentDiscount($offer): bool
    {
        return $offer->getOfferType()->equals(SpecialOfferType::TEN_PERCENT_DISCOUNT());
    }

    private function isSatisfyTwoForAmountOffering(int $quantity): bool
    {
        return $quantity >= self::MINIMUM_QUANTITY_FOR_OFFERING;
    }

    private function isSatisfyThreeForTwoOffering(int $quantity): bool
    {
        return $quantity > 2;
    }

    private function isSatisfyFiveForAmountOffering(int $quantity): bool
    {
        return $quantity >= 5;
    }
}
