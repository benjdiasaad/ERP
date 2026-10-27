const currencyFormatter = new Intl.NumberFormat('fr-MA', { style: 'currency', currency: 'MAD', maximumFractionDigits: 0 });
const preciseCurrencyFormatter = new Intl.NumberFormat('fr-MA', { style: 'currency', currency: 'MAD', minimumFractionDigits: 2 });
const numberFormatter = new Intl.NumberFormat('fr-FR');
const amountFormatter = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dateFormatter = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
const dateTimeFormatter = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });

export const formatCurrency = (value) => currencyFormatter.format(Number(value ?? 0));
/** With centimes, for documents (quotes, invoices). */
export const formatMoney = (value) => preciseCurrencyFormatter.format(Number(value ?? 0));
export const formatNumber = (value) => numberFormatter.format(Number(value ?? 0));
/** Two decimals followed by any currency code (bank accounts, cautions in EUR…). */
export const formatAmount = (value, currency = 'MAD') => `${amountFormatter.format(Number(value ?? 0))} ${currency ?? ''}`.trim();
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
    priority: { low: 'Basse', medium: 'Moyenne', high: 'Haute', urgent: 'Urgente' },
    purchaseRequestStatus: {
        draft: 'Brouillon', submitted: 'En attente', approved: 'Approuvée', rejected: 'Rejetée', converted: 'Commandée', cancelled: 'Annulée',
    },
    purchaseOrderStatus: {
        draft: 'Brouillon', sent: 'Envoyé', confirmed: 'Confirmé', in_progress: 'Réception en cours', received: 'Reçu', invoiced: 'Facturé', cancelled: 'Annulé',
    },
    receptionStatus: { draft: 'À contrôler', confirmed: 'Confirmée', cancelled: 'Annulée' },
    supplierType: { individual: 'Particulier', company: 'Entreprise' },
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
    deliveryStatus: { draft: 'Brouillon', ready: 'Prêt à expédier', shipped: 'Expédié', delivered: 'Livré', returned: 'Retourné' },
    creditNoteStatus: { draft: 'Brouillon', confirmed: 'Confirmé', applied: 'Imputé' },
    productType: { product: 'Produit', service: 'Service', consumable: 'Consommable' },
    movementType: { in: 'Entrée', out: 'Sortie', transfer: 'Transfert', adjustment: 'Ajustement', return: 'Retour', initial: 'Stock initial' },
    inventoryStatus: { draft: 'Brouillon', in_progress: 'Comptage en cours', validated: 'Validé', completed: 'Validé', cancelled: 'Annulé' },
    movementSource: {
        ReceptionNote: 'Réception', DeliveryNote: 'Livraison', StockInventory: 'Inventaire', transfer: 'Transfert',
    },
    accountType: { asset: 'Actif', liability: 'Passif', equity: 'Capitaux propres', revenue: 'Produits', expense: 'Charges' },
    journalStatus: { draft: 'Brouillon', posted: 'Comptabilisée', cancelled: 'Annulée' },
    cautionDirection: { given: 'Donnée', received: 'Reçue' },
    cautionPartner: { customer: 'Client', supplier: 'Fournisseur', other: 'Autre' },
    cautionStatus: {
        draft: 'Brouillon', active: 'Active', partially_returned: 'Restitution partielle', returned: 'Restituée',
        expired: 'Expirée', forfeited: 'Saisie', cancelled: 'Annulée',
    },
    cautionAction: {
        created: 'Création', updated: 'Modification', activated: 'Activation', partial_return: 'Restitution partielle',
        full_return: 'Restitution totale', extended: 'Prolongation', forfeited: 'Saisie', cancelled: 'Annulation', expired: 'Expiration',
    },
    personnelStatus: { active: 'Actif', inactive: 'Inactif', suspended: 'Suspendu', terminated: 'Sorti' },
    employmentType: { full_time: 'Temps plein', part_time: 'Temps partiel', freelance: 'Freelance', intern: 'Stagiaire' },
    gender: { male: 'Homme', female: 'Femme', other: 'Autre' },
    contractType: { CDI: 'CDI', CDD: 'CDD', stage: 'Stage', freelance: 'Freelance', interim: 'Intérim' },
    contractStatus: { draft: 'Brouillon', active: 'Actif', expired: 'Expiré', terminated: 'Résilié', suspended: 'Suspendu' },
    leaveType: { annual: 'Congé annuel', sick: 'Maladie', maternity: 'Maternité', paternity: 'Paternité', unpaid: 'Sans solde', compensatory: 'Récupération' },
    leaveStatus: { pending: 'En attente', approved: 'Approuvé', rejected: 'Refusé', cancelled: 'Annulé' },
    eventStatus: { planned: 'Planifié', confirmed: 'Confirmé', in_progress: 'En cours', completed: 'Terminé', cancelled: 'Annulé', postponed: 'Reporté' },
    participantRole: { organizer: 'Organisateur', speaker: 'Intervenant', attendee: 'Participant', guest: 'Invité' },
    rsvpStatus: { pending: 'Sans réponse', confirmed: 'Présent', declined: 'Absent' },
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
    priority: { urgent: 'destructive', high: 'destructive', medium: 'secondary', low: 'outline' },
    purchaseRequestStatus: { draft: 'outline', submitted: 'secondary', approved: 'default', rejected: 'destructive', converted: 'default', cancelled: 'outline' },
    purchaseOrderStatus: { draft: 'outline', sent: 'secondary', confirmed: 'secondary', in_progress: 'secondary', received: 'default', invoiced: 'default', cancelled: 'destructive' },
    receptionStatus: { draft: 'secondary', confirmed: 'default', cancelled: 'outline' },
    quoteStatus: { draft: 'outline', sent: 'secondary', accepted: 'default', converted: 'default', rejected: 'destructive', expired: 'outline' },
    orderStatus: { draft: 'outline', confirmed: 'secondary', in_progress: 'secondary', delivered: 'default', invoiced: 'default', cancelled: 'destructive' },
    invoiceStatus: { draft: 'outline', sent: 'secondary', partial: 'secondary', paid: 'default', overdue: 'destructive', cancelled: 'outline' },
    deliveryStatus: { draft: 'outline', ready: 'secondary', shipped: 'secondary', delivered: 'default', returned: 'destructive' },
    creditNoteStatus: { draft: 'outline', confirmed: 'secondary', applied: 'default' },
    movementType: { in: 'default', return: 'default', out: 'destructive', transfer: 'secondary', adjustment: 'outline', initial: 'outline' },
    inventoryStatus: { draft: 'outline', in_progress: 'secondary', validated: 'default', completed: 'default', cancelled: 'destructive' },
    journalStatus: { draft: 'outline', posted: 'default', cancelled: 'destructive' },
    personnelStatus: { active: 'secondary', inactive: 'outline', suspended: 'destructive', terminated: 'outline' },
    contractStatus: { draft: 'outline', active: 'default', expired: 'outline', terminated: 'destructive', suspended: 'secondary' },
    leaveStatus: { pending: 'secondary', approved: 'default', rejected: 'destructive', cancelled: 'outline' },
    eventStatus: { planned: 'outline', confirmed: 'default', in_progress: 'secondary', completed: 'secondary', cancelled: 'destructive', postponed: 'secondary' },
    rsvpStatus: { pending: 'outline', confirmed: 'default', declined: 'destructive' },
    cautionStatus: {
        draft: 'outline', active: 'default', partially_returned: 'secondary', returned: 'secondary',
        expired: 'destructive', forfeited: 'destructive', cancelled: 'outline',
    },
};

export const variant = (group, value) => variants[group]?.[value] ?? 'secondary';

export const label = (group, value) => labels[group]?.[value] ?? value ?? '—';
