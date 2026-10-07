import 'package:shared_preferences/shared_preferences.dart';

/// Same idea as SaleOrderRemembered, kept as a separate key namespace since
/// a booker's usual branch/currency for returns isn't necessarily the same
/// as for new orders.
class SaleReturnRemembered {
  static const _companyKey = 'sale_return_remembered_company_id';
  static const _branchKey = 'sale_return_remembered_branch_id';
  static const _currencyKey = 'sale_return_remembered_currency_id';

  static Future<int?> getCompanyId() async =>
      (await SharedPreferences.getInstance()).getInt(_companyKey);
  static Future<int?> getBranchId() async =>
      (await SharedPreferences.getInstance()).getInt(_branchKey);
  static Future<int?> getCurrencyId() async =>
      (await SharedPreferences.getInstance()).getInt(_currencyKey);

  static Future<void> setCompanyId(int id) async =>
      (await SharedPreferences.getInstance()).setInt(_companyKey, id);
  static Future<void> setBranchId(int id) async =>
      (await SharedPreferences.getInstance()).setInt(_branchKey, id);
  static Future<void> setCurrencyId(int id) async =>
      (await SharedPreferences.getInstance()).setInt(_currencyKey, id);
}
