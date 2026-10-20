<?php
/** Marketing Booster: server-side printing quote calculations (PKR). */
function mb_amount($value, $label, $allowZero = true) {
    if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0 || (!$allowZero && (float)$value == 0)) {
        throw new InvalidArgumentException("Invalid $label");
    }
    return (float)$value;
}
function mb_calculate_item(array $item) {
    $method = $item['pricing_method'] ?? 'fixed';
    if (!in_array($method, ['square_foot', 'fixed', 'per_piece', 'custom'], true)) throw new InvalidArgumentException('Invalid pricing method');
    $qty = mb_amount($item['quantity'] ?? 1, 'quantity', false);
    $rate = mb_amount($item['rate'] ?? 0, 'rate');
    $design = mb_amount($item['design_charges'] ?? 0, 'design charges');
    $extra = mb_amount($item['additional_charges'] ?? 0, 'additional charges');
    $discount = mb_amount($item['discount'] ?? 0, 'discount');
    $area = 0;
    if ($method === 'square_foot') {
        $unit = $item['unit'] ?? 'feet';
        if (!in_array($unit, ['feet', 'inches'], true)) throw new InvalidArgumentException('Invalid measurement unit');
        $width = mb_amount($item['width'] ?? null, 'width', false);
        $height = mb_amount($item['height'] ?? null, 'height', false);
        $area = ($width / ($unit === 'inches' ? 12 : 1)) * ($height / ($unit === 'inches' ? 12 : 1));
    }
    $base = $method === 'square_foot' ? $area * $qty * $rate : ($method === 'per_piece' ? $qty * $rate : $rate);
    $total = round($base + $design + $extra - $discount, 2);
    if ($total < 0) throw new InvalidArgumentException('Discount exceeds item charges');
    return ['area_per_item' => round($area, 4), 'total_area' => round($area * $qty, 4), 'printing_charges' => round($base, 2), 'design_charges' => round($design, 2), 'additional_charges' => round($extra, 2), 'discount' => round($discount, 2), 'total' => $total];
}
function mb_calculate_order(array $items, $discount = 0, $otherCharges = 0, $paid = 0) {
    if (!$items) throw new InvalidArgumentException('At least one item required');
    $lines = array_map('mb_calculate_item', $items);
    $subtotal = round(array_sum(array_column($lines, 'total')), 2);
    $total = round($subtotal - mb_amount($discount, 'order discount') + mb_amount($otherCharges, 'other charges'), 2);
    if ($total < 0) throw new InvalidArgumentException('Order discount exceeds charges');
    $paid = mb_amount($paid, 'paid');
    if (round($paid - $total, 2) > 0) throw new InvalidArgumentException('Payment exceeds invoice total');
    $balance = round($total - $paid, 2);
    return ['items' => $lines, 'subtotal' => $subtotal, 'total' => $total, 'paid' => round($paid, 2), 'balance' => $balance, 'payment_status' => $balance == 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid')];
}
