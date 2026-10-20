<?php
require __DIR__ . '/../config/printing_calculator.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "PASS: $message\n"; }
$item = ['pricing_method'=>'square_foot','width'=>10,'height'=>5,'unit'=>'feet','quantity'=>2,'rate'=>200,'design_charges'=>1000,'additional_charges'=>500,'discount'=>500];
$result = mb_calculate_order([$item], 0, 0, 8000);
check($result['items'][0]['area_per_item'] === 50.0 && $result['items'][0]['total_area'] === 100.0, 'Panaflex area');
check($result['items'][0]['printing_charges'] === 20000.0, 'Panaflex printing price');
check($result['total'] === 21000.0 && $result['balance'] === 13000.0 && $result['payment_status'] === 'Partial', 'Invoice and advance');
$item['unit']='inches'; $item['width']=120; $item['height']=60; $item['quantity']=1; $item['design_charges']=0; $item['additional_charges']=0; $item['discount']=0;
check(mb_calculate_item($item)['printing_charges'] === 10000.0, 'Inches conversion');
check(mb_calculate_order([['pricing_method'=>'per_piece','quantity'=>500,'rate'=>2], ['pricing_method'=>'fixed','rate'=>1000]], 0, 0, 2000)['payment_status'] === 'Paid', 'Multiple pricing methods');
try { mb_calculate_item(['pricing_method'=>'square_foot','width'=>-1,'height'=>5,'rate'=>200]); throw new RuntimeException('Negative width accepted'); } catch (InvalidArgumentException $e) { check(true, 'Reject negative dimensions'); }
