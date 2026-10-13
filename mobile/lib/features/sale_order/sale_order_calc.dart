/// Mirrors the EXACT formulas in client/assets/js/sale/sale_order/order-add-uom.js
/// (calculateRowAmounts / updateInvoiceSummaryDynamic) so a mobile-created
/// order's numbers always match what the web app would have computed for
/// the same inputs — not an approximation.
class OrderLineCalc {
  final double quantity;
  final double salePrice;
  final double discountPercent;
  final double tradeOfferAmount;
  final double gstPercent;
  final double focQuantity;

  const OrderLineCalc({
    required this.quantity,
    required this.salePrice,
    this.discountPercent = 0,
    this.tradeOfferAmount = 0,
    this.gstPercent = 0,
    this.focQuantity = 0,
  });

  double get grossAmount => quantity * salePrice;
  double get discountAmount => grossAmount * (discountPercent / 100);
  double get _afterDiscount => grossAmount - discountAmount;
  double get _afterTradeOffer => _afterDiscount - tradeOfferAmount;
  double get gstAmount => _afterTradeOffer * (gstPercent / 100);
  double get netAmount => _afterTradeOffer + gstAmount;
}

class OrderTotalsCalc {
  final double totalBill; // sum of all line netAmounts
  final double discountPercent;
  final double gstPercent;
  final double shippingFees;

  const OrderTotalsCalc({
    required this.totalBill,
    this.discountPercent = 0,
    this.gstPercent = 0,
    this.shippingFees = 0,
  });

  double get discountAmount => totalBill * (discountPercent / 100);
  double get _afterDiscount => totalBill - discountAmount;
  double get gstAmount => _afterDiscount * (gstPercent / 100);
  double get netAmount => _afterDiscount + gstAmount + shippingFees;
}
