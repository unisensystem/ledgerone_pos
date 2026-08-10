// Replace lines 565-577 in supplier-ledger.js with this:

                    tbody.innerHTML += `
                        <tr ${isNonApprovedPDC ? 'style="color: #e8b23f; font-style: italic;"' : ''}>
                            <td>${row.date}</td>
                            <td>
                                ${row.description}
                                ${row.invoice_id || row.return_id ? `<button class="btn-expand" data-type="${row.invoice_id ? 'invoice' : 'return'}" data-id="${row.invoice_id || row.return_id}" style="margin-left: 8px; padding: 2px 8px; font-size: 11px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Expand</button>` : ''}
                            </td>
                            <td>${row.reference}</td>
                            <td>${debit > 0 ? currentCurrencySymbol + debit.toFixed(2) : ''}</td>
                            <td>${credit > 0 ? currentCurrencySymbol + credit.toFixed(2) : ''}</td>
                            <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
                        </tr>
                    `;

// Replace lines 607-619 in supplier-ledger.js with this (inside subAccountTransactions.forEach):

                    tbody.innerHTML += `
                        <tr ${isNonApprovedPDC ? 'style="color: #e8b23f; font-style: italic;"' : ''}>
                            <td>${row.date}</td>
                            <td>
                                ${row.description}
                                ${row.invoice_id || row.return_id ? `<button class="btn-expand" data-type="${row.invoice_id ? 'invoice' : 'return'}" data-id="${row.invoice_id || row.return_id}" style="margin-left: 8px; padding: 2px 8px; font-size: 11px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Expand</button>` : ''}
                            </td>
                            <td>${row.reference}</td>
                            <td>${debit > 0 ? currentCurrencySymbol + debit.toFixed(2) : ''}</td>
                            <td>${credit > 0 ? currentCurrencySymbol + credit.toFixed(2) : ''}</td>
                            <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
                        </tr>
                    `;

// Replace lines 643-655 in supplier-ledger.js with this (inside remaining sub accounts):

                    tbody.innerHTML += `
                        <tr ${isNonApprovedPDC ? 'style="color: #e8b23f; font-style: italic;"' : ''}>
                            <td>${row.date}</td>
                            <td>
                                ${row.description}
                                ${row.invoice_id || row.return_id ? `<button class="btn-expand" data-type="${row.invoice_id ? 'invoice' : 'return'}" data-id="${row.invoice_id || row.return_id}" style="margin-left: 8px; padding: 2px 8px; font-size: 11px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Expand</button>` : ''}
                            </td>
                            <td>${row.reference}</td>
                            <td>${debit > 0 ? currentCurrencySymbol + debit.toFixed(2) : ''}</td>
                            <td>${credit > 0 ? currentCurrencySymbol + credit.toFixed(2) : ''}</td>
                            <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
                        </tr>
                    `;

// Replace lines 662-668 in supplier-ledger.js with this (totals row):

        const finalBalance = openingBalance - totalCredit + totalDebit;
        tbody.innerHTML += `
            <tr class="totals-row">
                <td><strong>Totals</strong></td>
                <td></td>
                <td></td>
                <td><strong>${currentCurrencySymbol}${totalDebit.toFixed(2)}</strong></td>
                <td><strong>${currentCurrencySymbol}${totalCredit.toFixed(2)}</strong></td>
                <td><strong class="${finalBalance >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(finalBalance).toFixed(2)} ${finalBalance >= 0 ? 'Dr' : 'Cr'}</strong></td>
            </tr>
        `;
