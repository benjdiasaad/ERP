/**
 * Line and document totals for sales documents. Mirrors
 * QuoteService::calculateLineAmounts / calculateTotals so the form preview
 * matches what the server stores.
 */
const round = (value) => Math.round((value + Number.EPSILON) * 100) / 100;

export function lineAmounts(line) {
    const quantity = Number(line.quantity) || 0;
    const unitPrice = Number(line.unit_price_ht) || 0;
    const discountValue = Number(line.discount_value) || 0;
    const taxRate = Number(line.tax_rate) || 0;

    const subtotal = quantity * unitPrice;
    const discount = line.discount_type === 'fixed' ? discountValue : subtotal * (discountValue / 100);
    const afterDiscount = subtotal - discount;
    const tax = afterDiscount * (taxRate / 100);

    return {
        subtotal: round(subtotal),
        discount: round(discount),
        afterDiscount: round(afterDiscount),
        tax: round(tax),
        total: round(afterDiscount + tax),
    };
}

export function documentTotals(lines) {
    const totals = lines.map(lineAmounts).reduce((sum, l) => ({
        subtotal: sum.subtotal + l.subtotal,
        discount: sum.discount + l.discount,
        tax: sum.tax + l.tax,
    }), { subtotal: 0, discount: 0, tax: 0 });

    return {
        subtotal: round(totals.subtotal),
        discount: round(totals.discount),
        tax: round(totals.tax),
        total: round(totals.subtotal - totals.discount + totals.tax),
    };
}
