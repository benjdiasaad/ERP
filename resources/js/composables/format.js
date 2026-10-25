const currencyFormatter = new Intl.NumberFormat('fr-MA', { style: 'currency', currency: 'MAD', maximumFractionDigits: 0 });
const preciseCurrencyFormatter = new Intl.NumberFormat('fr-MA', { style: 'currency', currency: 'MAD', minimumFractionDigits: 2 });
const numberFormatter = new Intl.NumberFormat('fr-FR');
const dateFormatter = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
const dateTimeFormatter = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });

export const formatCurrency = (value) => currencyFormatter.format(Number(value ?? 0));
/** With centimes, for documents (quotes, invoices). */
export const formatMoney = (value) => preciseCurrencyFormatter.format(Number(value ?? 0));
export const formatNumber = (value) => numberFormatter.format(Number(value ?? 0));
export const formatPercent = (value) => `${numberFormatter.format(Number(value ?? 0))} %`;
export const formatDate = (value) => (value ? dateFormatter.format(new Date(value)) : '—');
export const formatDateTime = (value) => (value ? dateTimeFormatter.format(new Date(value)) : '—');

/** ISO timestamp → value for an <input type="datetime-local"> in the browser's timezone. */
export const toLocalDateTimeInput = (value) => {
    if (!value) return null;
    const date = new Date(value);
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

/** datetime-local value (browser time) → ISO timestamp, so the server stores the right instant. */
export const toServerDateTime = (value) => (value ? new Date(value).toISOString() : null);

/** French labels for enum values coming from the API. */
export const labels = {
    source: {
        website: 'Site web', referral: 'Recommandation', social_media: 'Réseaux sociaux',
        cold_call: 'Prospection', trade_show: 'Salon', advertisement: 'Publicité', other: 'Autre',
    },
    stage: {
        prospecting: 'Prospection', qualification: 'Qualification', proposal: 'Proposition',
        negotiation: 'Négociation', closed_won: 'Gagnée', closed_lost: 'Perdue',
    },
    leadStatus: {
        new: 'Nouveau', contacted: 'Contacté', qualified: 'Qualifié',
        unqualified: 'Non qualifié', converted: 'Converti', lost: 'Perdu',
    },
    activityType: {
        call: 'Appel', email: 'E-mail', meeting: 'Réunion', task: 'Tâche', note: 'Note', follow_up: 'Relance',
    },
    activityStatus: {
        pending: 'À faire', in_progress: 'En cours', completed: 'Terminée', cancelled: 'Annulée',
    },
    priority: { low: 'Basse', medium: 'Moyenne', high: 'Haute' },
    quoteStatus: {
        draft: 'Brouillon', sent: 'Envoyé', accepted: 'Accepté', rejected: 'Refusé', expired: 'Expiré', converted: 'Converti',
    },
    customerType: { individual: 'Particulier', company: 'Entreprise' },
    orderStatus: {
        draft: 'Brouillon', confirmed: 'Confirmée', in_progress: 'En cours', delivered: 'Livrée', invoiced: 'Facturée', cancelled: 'Annulée',
    },
    invoiceStatus: {
        draft: 'Brouillon', sent: 'Envoyée', partial: 'Paiement partiel', paid: 'Payée', overdue: 'En retard', cancelled: 'Annulée',
    },
    deliveryStatus: { draft: 'Brouillon', ready: 'Prête', shipped: 'Expédiée', delivered: 'Livrée', returned: 'Retournée', cancelled: 'Annulée' },
    paymentMethod: { bank_transfer: 'Virement', check: 'Chèque', cash: 'Espèces', credit_card: 'Carte bancaire', mobile: 'Paiement mobile' },
    eventType: {
        meeting: 'Réunion', conference: 'Conférence', training: 'Formation',
        workshop: 'Atelier', social: 'Social', holiday: 'Congé',
    },
};

/** Badge variants for statuses, shared by the CRM screens. */
export const variants = {
    leadStatus: { new: 'outline', contacted: 'secondary', qualified: 'default', unqualified: 'outline', converted: 'default', lost: 'destructive' },
    stage: { closed_won: 'default', closed_lost: 'destructive' },
    activityStatus: { completed: 'default', cancelled: 'outline', in_progress: 'secondary', pending: 'outline' },
    priority: { high: 'destructive', medium: 'secondary', low: 'outline' },
    quoteStatus: { draft: 'outline', sent: 'secondary', accepted: 'default', converted: 'default', rejected: 'destructive', expired: 'outline' },
    orderStatus: { draft: 'outline', confirmed: 'secondary', in_progress: 'secondary', delivered: 'default', invoiced: 'default', cancelled: 'destructive' },
    invoiceStatus: { draft: 'outline', sent: 'secondary', partial: 'secondary', paid: 'default', overdue: 'destructive', cancelled: 'outline' },
};

export const variant = (group, value) => variants[group]?.[value] ?? 'secondary';

export const label = (group, value) => labels[group]?.[value] ?? value ?? '—';
