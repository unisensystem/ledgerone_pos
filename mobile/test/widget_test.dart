import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:ledgerone_mobile/main.dart';

void main() {
  testWidgets('App boots to the Login screen', (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: LedgerOneMobileApp()));

    expect(find.text('LedgerOne Mobile'), findsOneWidget);
    expect(find.text('Sign in to continue'), findsOneWidget);
    expect(find.widgetWithText(ElevatedButton, 'Sign In'), findsOneWidget);
  });
}
