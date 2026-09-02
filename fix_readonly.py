import re

file = r'C:\xampp\htdocs\ledgerone_erp\client\assets\css\sale\med_pos_invoice\pos-add.css'

with open(file, 'r', encoding='utf-8') as f:
    content = f.read()

# Change color: var(--subtext) to color: var(--body) inside input:read-only / select:read-only block
old = 'input:read-only,'
# Find the block and replace color: var(--subtext) with color: var(--body)
new_content = re.sub(
    r'(input:read-only[\s\S]*?cursor:\s*not-allowed;[\s\S]*?})',
    lambda m: m.group(0).replace('color: var(--subtext)', 'color: var(--body)'),
    content,
    count=1
)

if new_content != content:
    with open(file, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Fixed: color: var(--subtext) -> color: var(--body) in read-only block")
else:
    print("No change made — pattern not found")
