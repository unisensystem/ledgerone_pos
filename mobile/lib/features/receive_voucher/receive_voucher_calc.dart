/// Mirrors receive-add.js's updateRemainingBalance() exactly.
class ReceiveVoucherCalc {
  final double previousBalance;
  final double amount;
  final double adjustAmount;

  const ReceiveVoucherCalc({
    required this.previousBalance,
    required this.amount,
    this.adjustAmount = 0,
  });

  double get remainingBalance => previousBalance - amount - adjustAmount;
}
