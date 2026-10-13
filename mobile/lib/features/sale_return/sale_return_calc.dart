/// Mirrors the formulas used by counter-return.js — the ONLY proven-correct
/// reference implementation on web for Sale Return (see sale_return_api.dart
/// for why return-add.js itself is not the reference to copy). Return-level
/// trade offer is PERCENT-driven here, unlike Sale Order's amount-driven
/// trade offer — confirmed against counter-return.js's own calculation
/// chain, not assumed from Sale Order's shape.
class ReturnLineCalc {
  final double quantity;
  final double salePrice;
  final double discountPercent;
  final double tradeOfferPercent;
  final double taxPercent;
  final double focQuantity;

  const ReturnLineCalc({
    required this.quantity,
    required this.salePrice,
    this.discountPercent = 0,
    this.tradeOfferPercent = 0,
    this.taxPercent = 0,
    this.focQuantity = 0,
  });

  double get grossAmount => quantity * salePrice;
  double get discountAmount => grossAmount * (discountPercent / 100);
  double get _afterDiscount => grossAmount - discountAmount;
  double get tradeOfferAmount => _afterDiscount * (tradeOfferPercent / 100);
  double get _afterTradeOffer => _afterDiscount - tradeOfferAmount;
  double get taxAmount => _afterTradeOffer * (taxPercent / 100);
  double get netAmount => _afterTradeOffer + taxAmount;
}

/// sale_return has no header-level GST column (confirmed against
/// return-add.php's INSERT column list — only total_discount_percent/
/// amount and shipping_fees exist at header level; GST/Tax only ever lives
/// per item and is already folded into each line's netAmount).
class ReturnTotalsCalc {
  final double totalBill; // sum of all line netAmounts
  final double discountPercent;
  final double shippingFees;

  const ReturnTotalsCalc({
    required this.totalBill,
    this.discountPercent = 0,
    this.shippingFees = 0,
  });

  double get discountAmount => totalBill * (discountPercent / 100);
  double get netAmount => (totalBill - discountAmount) + shippingFees;
}
