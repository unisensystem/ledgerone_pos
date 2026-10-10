import 'package:flutter/material.dart';

/// Reusable type-to-search dropdown, built on Flutter's own Autocomplete
/// widget rather than a DropdownButtonFormField. Originally written for Sale
/// Order's Customer/Branch fields; this ALSO fixes a real bug plain
/// dropdowns had: DropdownButtonFormField only shows a value as "selected"
/// when it is `==`-identical to one of the objects in its `items` list.
/// Since option lists here are often freshly-built Map objects on every
/// rebuild, a picked value could never match by reference/equality again,
/// so the field silently reverted to showing nothing picked even though a
/// real selection was stored. Autocomplete manages its own display text
/// directly from the selected option, so this class of bug can't recur.
Widget searchableField<T extends Object>({
  Key? key,
  required String label,
  required List<T> options,
  required String Function(T) displayStringForOption,
  required bool Function(T option, String query) matches,
  required ValueChanged<T> onSelected,
  String? initialText,
}) {
  return Autocomplete<T>(
    key: key,
    displayStringForOption: displayStringForOption,
    initialValue: initialText != null
        ? TextEditingValue(text: initialText)
        : null,
    optionsBuilder: (textEditingValue) {
      final query = textEditingValue.text.toLowerCase();
      if (query.isEmpty) return options;
      return options.where((o) => matches(o, query));
    },
    onSelected: onSelected,
    fieldViewBuilder: (context, controller, focusNode, onFieldSubmitted) {
      return TextField(
        controller: controller,
        focusNode: focusNode,
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
          filled: true,
          fillColor: Colors.white,
          suffixIcon: const Icon(Icons.search, size: 20),
        ),
      );
    },
    optionsViewBuilder: (context, onSelectedCb, optionsList) {
      final list = optionsList.toList();
      return Align(
        alignment: Alignment.topLeft,
        child: Material(
          elevation: 4,
          borderRadius: BorderRadius.circular(8),
          child: ConstrainedBox(
            constraints: const BoxConstraints(
              maxHeight: 280,
              minWidth: 280,
              maxWidth: 560,
            ),
            child: list.isEmpty
                ? const Padding(
                    padding: EdgeInsets.all(16),
                    child: Text(
                      'No matches',
                      style: TextStyle(color: Color(0xFF6B7280)),
                    ),
                  )
                : ListView.builder(
                    padding: EdgeInsets.zero,
                    shrinkWrap: true,
                    itemCount: list.length,
                    itemBuilder: (context, index) {
                      final option = list[index];
                      return ListTile(
                        dense: true,
                        title: Text(
                          displayStringForOption(option),
                          overflow: TextOverflow.ellipsis,
                        ),
                        onTap: () => onSelectedCb(option),
                      );
                    },
                  ),
          ),
        ),
      );
    },
  );
}
