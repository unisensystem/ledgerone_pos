import 'package:shared_preferences/shared_preferences.dart';

/// Remembers the last-picked Company/Branch/Currency on this device, so a
/// booker who always works from the same branch doesn't have to re-pick it
/// on every single order. Saved the moment each one is picked (not just on
/// submit), and paired with the showCompanyField/showBranchField/
/// showCurrencyField toggles in SaleOrderFieldSettings — once a booker is
/// happy with what's remembered, they can hide the field entirely and the
/// remembered value just keeps being used silently.
class SaleOrderRemembered {
  static const _companyKey = 'sale_order_remembered_company_id';
  static const _branchKey = 'sale_order_remembered_branch_id';
  static const _currencyKey = 'sale_order_remembered_currency_id';

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
