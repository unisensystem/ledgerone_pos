files = [
    r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\med_pos_invoice\pos-add.js',
    r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\pos_invoice\pos-add.js',
    r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\sale_tax_invoice\pos-add.js',
    r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\purchase\purchase_invoice\purchase-add.js',
]

# Fix updateSelectionDynamic - same in all files
old_selection = "            option.style.backgroundColor = index === selectedIndex ? 'var(--surface-1)' : '';"
new_selection = "            option.style.backgroundColor = index === selectedIndex ? '#1a6cdc' : '';\n            option.style.color = index === selectedIndex ? 'white' : '';"

# Fix filterOptionsDynamic - sale files (8-space indent inside function)
old_filter_sale = """        for (let i = 0; i < options.length; i++) {
            const option = options[i];
            const text = option.textContent.toLowerCase();
            
            if (text.includes(searchTerm)) {
                option.style.display = 'block';
                option.style.backgroundColor = '';
            } else {
                option.style.display = 'none';
            }
        }
    }"""

new_filter_sale = """        for (let i = 0; i < options.length; i++) {
            const option = options[i];
            const text = option.textContent.toLowerCase();
            option.style.display = text.includes(searchTerm) ? 'block' : 'none';
        }
        // Re-apply highlight after filtering
        const visibleOpts = Array.from(optionsContainer.getElementsByClassName('dropdown-option'))
            .filter(o => o.style.display !== 'none');
        visibleOpts.forEach((o, i) => {
            o.style.backgroundColor = i === selectedIndex ? '#1a6cdc' : '';
            o.style.color = i === selectedIndex ? 'white' : '';
        });
    }"""

# Fix filterOptionsDynamic - purchase file (12-space indent inside function)
old_filter_purchase = """            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const text = option.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    option.style.display = 'block';
                    option.style.backgroundColor = '';
                } else {
                    option.style.display = 'none';
                }
            }
        }
    }"""

new_filter_purchase = """            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(searchTerm) ? 'block' : 'none';
            }
            // Re-apply highlight after filtering
            const visibleOpts = Array.from(optionsContainer.getElementsByClassName('dropdown-option'))
                .filter(o => o.style.display !== 'none');
            visibleOpts.forEach((o, i) => {
                o.style.backgroundColor = i === selectedIndex ? '#1a6cdc' : '';
                o.style.color = i === selectedIndex ? 'white' : '';
            });
        }
    }"""

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()

    content = content.replace(old_selection, new_selection)

    if 'purchase' in file:
        content = content.replace(old_filter_purchase, new_filter_purchase)
    else:
        content = content.replace(old_filter_sale, new_filter_sale)

    with open(file, 'w', encoding='utf-8') as f:
        f.write(content)

    print(f"Done: {file}")
