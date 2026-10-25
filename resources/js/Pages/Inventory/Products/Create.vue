<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ProductForm from './Partials/ProductForm.vue';

defineProps({
    categories: { type: Array, required: true },
});

const form = useForm({
    name: '', code: '', type: 'product', category_id: null, unit: 'pièce', barcode: '', description: '',
    purchase_price: 0, sale_price: 0, tax_rate: 20, min_stock_level: 0, reorder_point: 0, max_stock_level: 0,
    is_purchasable: true, is_sellable: true, is_stockable: true, is_active: true, notes: '',
});

const submit = () => form.post(route('stock.products.store'));
</script>

<template>
    <AppLayout title="Nouveau produit" :breadcrumbs="[{ title: 'Stock' }, { title: 'Produits', href: route('stock.products.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouveau produit" />
        <ProductForm :form="form" :categories="categories" submit-label="Créer le produit"
            :cancel-href="route('stock.products.index')" @submit="submit" />
    </AppLayout>
</template>
