import {
    LayoutDashboard, Users, Building2, ShoppingCart, FileText, Truck, Package, Warehouse,
    Landmark, BookOpen, ShieldCheck, CalendarDays, Contact, Target, TrendingUp, ListTodo,
    FolderKanban, UserCog, Settings, FileMinus,
} from '@lucide/vue';

/**
 * Sidebar structure. `route` is a Ziggy route name; items without a route
 * are modules whose screens are not built yet (shown as "Bientôt").
 * `permission` hides the item when the user lacks it in the current company.
 */
export const navigation = [
    {
        label: null,
        items: [{ label: 'Tableau de bord', route: 'dashboard', icon: LayoutDashboard }],
    },
    {
        label: 'CRM',
        items: [
            { label: 'Contacts', route: 'crm.contacts.index', active: 'crm.contacts.*', permission: 'contacts.view_any', icon: Contact },
            { label: 'Leads', route: 'crm.leads.index', active: 'crm.leads.*', permission: 'leads.view_any', icon: Target },
            { label: 'Opportunités', route: 'crm.opportunities.index', active: 'crm.opportunities.*', permission: 'opportunities.view_any', icon: TrendingUp },
            { label: 'Activités', route: 'crm.activities.index', active: 'crm.activities.*', permission: 'activities.view_any', icon: ListTodo },
        ],
    },
    {
        label: 'Ventes',
        items: [
            { label: 'Clients', route: 'sales.customers.index', active: 'sales.customers.*', permission: 'customers.view_any', icon: Users },
            { label: 'Devis', route: 'sales.quotes.index', active: 'sales.quotes.*', permission: 'quotes.view_any', icon: FileText },
            { label: 'Commandes', route: 'sales.orders.index', active: 'sales.orders.*', permission: 'sales_orders.view_any', icon: ShoppingCart },
            { label: 'Factures', route: 'sales.invoices.index', active: 'sales.invoices.*', permission: 'invoices.view_any', icon: FileText },
            { label: 'Bons de livraison', route: 'sales.delivery-notes.index', active: 'sales.delivery-notes.*', permission: 'delivery_notes.view_any', icon: Truck },
            { label: 'Avoirs', route: 'sales.credit-notes.index', active: 'sales.credit-notes.*', permission: 'credit_notes.view_any', icon: FileMinus },
        ],
    },
    {
        label: 'Achats',
        items: [
            { label: 'Fournisseurs', permission: 'suppliers.view_any', icon: Building2 },
            { label: 'Bons de commande', permission: 'purchase_orders.view_any', icon: ShoppingCart },
            { label: 'Factures fournisseurs', permission: 'purchase_invoices.view_any', icon: FileText },
        ],
    },
    {
        label: 'Stock',
        items: [
            { label: 'Produits', permission: 'products.view_any', icon: Package },
            { label: 'Entrepôts', permission: 'warehouses.view_any', icon: Warehouse },
        ],
    },
    {
        label: 'Finance',
        items: [
            { label: 'Comptes bancaires', permission: 'bank_accounts.view_any', icon: Landmark },
            { label: 'Écritures', permission: 'journal_entries.view_any', icon: BookOpen },
            { label: 'Cautions', permission: 'cautions.view_any', icon: ShieldCheck },
        ],
    },
    {
        label: 'Organisation',
        items: [
            { label: 'Personnel', permission: 'personnels.view_any', icon: UserCog },
            { label: 'Événements', permission: 'events.view_any', icon: CalendarDays },
            { label: 'Projets', permission: 'projects.view_any', icon: FolderKanban },
            { label: 'Paramètres', permission: 'settings.view_any', icon: Settings },
        ],
    },
];
