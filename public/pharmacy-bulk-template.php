<?php
// public/pharmacy-bulk-template.php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="pharmacy_import_template.csv"');

$headers = [
  'sku',
  'category',
  'name',
  'brand',
  'generic_name',
  'dosage_form',
  'strength',
  'requires_prescription', // 0 or 1
  'expiry_date',           // YYYY-MM-DD
  'batch_number',
  'price',
  'markup_price',
  'quantity',
  'low_stock_threshold',
  'description',
  'image_filename',        // matches a file in the ZIP
  'image_url'              // external URL (used if image_filename missing)
];

$out = fopen('php://output', 'w');
fputcsv($out, $headers);

// sample rows
fputcsv($out, [
  'PCT-500-TBL', 'Pain Relief', 'Panadol Extra', 'Panadol', 'Paracetamol',
  'tablet', '500 mg', 0, '2026-06-30', 'BATCH-A1', '1200', '1500', '30', '5',
  'Fast-acting pain relief', 'panadol_extra.jpg', ''
]);

fputcsv($out, [
  '', 'Antibiotics', '', 'Augmentin', 'Amoxicillin/Clavulanate',
  'tablet', '625 mg', 1, '2025-02-15', 'BATCH-B2', '3800', '', '12', '4',
  'Prescription required', '', 'https://example.com/images/augmentin.jpg'
]);

fclose($out);
