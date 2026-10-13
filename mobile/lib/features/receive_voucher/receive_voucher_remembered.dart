import 'package:shared_preferences/shared_preferences.dart';

/// Same idea as SaleOrderRemembered/SaleReturnRemembered — remembers the
/// last-picked Company/Currency on this device so a booker who always works
/// from the same company/currency doesn't re-pick it every voucher.
class ReceiveVoucherRemembered {
  static const _companyKey = 'receive_voucher_remembered_company_id';
  static const _currencyKey = 'receive_voucher_remembered_currency_id';

  static Future<int?> getCompanyId() async =>
      (await SharedPreferences.getInstance()).getInt(_companyKey);
  static Future<int?> getCurrencyId() async =>
      (await SharedPreferences.getInstance()).getInt(_currencyKey);

  static Future<void> setCompanyId(int id) async =>
      (await SharedPreferences.getInstance()).setInt(_companyKey, id);
  static Future<void> setCurrencyId(int id) async =>
      (await SharedPreferences.getInstance()).setInt(_currencyKey, id);
}
