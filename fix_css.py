import re

file = r'C:\xampp\htdocs\ledgerone_erp\client\assets\css\sale\med_pos_invoice\pos-add.css'

with open(file, 'r', encoding='utf-8') as f:
    content = f.read()

# Normalize line endings for processing
content = content.replace('\r\n', '\n')

# List of (pattern, replacement) — strip gradient/color from cell inputs, keep structural props
rules = [
    # price-cell: remove gradient bg, border-color, color — keep font-weight
    (r'(\.price-cell input\s*\{)[^}]*(font-weight\s*:\s*600\s*;)[^}]*(\})',
     r'\1\n  \2\n\3'),
    # price-cell focus: remove entirely
    (r'\.price-cell input:focus\s*\{[^}]*\}', ''),
    # gross-cell: keep font-weight
    (r'(\.gross-cell input\s*\{)[^}]*(font-weight\s*:\s*600\s*;)[^}]*(\})',
     r'\1\n  \2\n\3'),
    # disc cells: remove all color props
    (r'(\.disc-percent-cell input,\s*\.disc-amount-cell input\s*\{)[^}]*(\})',
     r'\1\2'),
    # to cells: remove all color props
    (r'(\.to-percent-cell input,\s*\.to-amount-cell input\s*\{)[^}]*(\})',
     r'\1\2'),
    # gst cells: remove all color props
    (r'(\.gst-percent-cell input,\s*\.gst-amount-cell input\s*\{)[^}]*(\})',
     r'\1\2'),
    # tax cells: remove all color props
    (r'(\.tax-percent-cell input,\s*\.tax-amount-cell input\s*\{)[^}]*(\})',
     r'\1\2'),
    # foc-cell: remove all color props (keep nothing — size was excessive)
    (r'(\.foc-cell input\s*\{)[^}]*(\})',
     r'\1\2'),
    # net-cell: keep font-weight and font-size
    (r'(\.net-cell input\s*\{)[^}]*(font-weight\s*:\s*700\s*;)[^}]*(font-size\s*:\s*13px\s*;)[^}]*(\})',
     r'\1\n  \2\n  \3\n\4'),
    # td:nth-child(2) product name: keep font-weight, remove gradient/border/color
    (r'(td:nth-child\(2\) input,\s*td:nth-child\(2\) \.search-input\s*\{)[^}]*(\})',
     r'\1\n  font-weight: 600;\n\2'),
    # td:nth-child(2) focus: remove entirely
    (r'td:nth-child\(2\) input:focus,\s*td:nth-child\(2\) \.search-input:focus\s*\{[^}]*\}', ''),
    # td:nth-child(3) scheme: keep font-weight, remove gradient/border/color
    (r'(td:nth-child\(3\) input,\s*td:nth-child\(3\) select\s*\{)[^}]*(\})',
     r'\1\n  font-weight: 600;\n\2'),
    # td:nth-child(3) focus: remove entirely
    (r'td:nth-child\(3\) input:focus,\s*td:nth-child\(3\) select:focus\s*\{[^}]*\}', ''),
    # th:has(.unit-header) gradient background
    (r'(th:has\(\.unit-header\)\s*\{)[^}]*(border-bottom[^;]+;)[^}]*(position[^;]+;)[^}]*(\})',
     r'\1\n  \2\n  \3\n\4'),
]

for pattern, replacement in rules:
    new_content = re.sub(pattern, replacement, content, flags=re.DOTALL)
    if new_content != content:
        print(f"Fixed: {pattern[:50]!r}")
        content = new_content
    else:
        print(f"No match: {pattern[:50]!r}")

# Restore CRLF
content = content.replace('\n', '\r\n')

with open(file, 'w', encoding='utf-8') as f:
    f.write(content)

print("\nDone")
