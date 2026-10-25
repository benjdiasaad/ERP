<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ProductForm from './Partials/ProductForm.vue';

const props = defineProps({
    product: { type: Object, required: true },
    categories: { type: Array, required: true },
});

const p = props.product.data;
const form = useForm({
    name: p.name, code: p.code, type: p.type, category_id: p.category_id, unit: p.unit, barcode: p.barcode, description: p.description,
    purchase_price: Number(p.purchase_price), sale_price: Number(p.sale_price), tax_rate: Number(p.tax_rate),
    min_stock_level: Number(p.min_stock_level), reorder_point: Number(p.reorder_point), max_stock_level: Number(p.max_stock_level),
    is_purchasable: p.is_purchasable, is_sellable: p.is_sellable, is_stockable: p.is_stockable, is_active: p.is_active, notes: p.notes,
});

const submit = () => form.put(route('stock.products.update', p.id));
</script>

<template>
    <AppLayout :title="`Modifier ${p.name}`" :breadcrumbs="[
        { title: 'Stock' }, { title: 'Produits', href: route('stock.products.index') },
        { title: p.name, href: route('stock.products.show', p.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${p.name}`" :description="p.code" />
        <ProductForm :form="form" :categories="categories" submit-label="Enregistrer"
            :cancel-href="route('stock.products.show', p.id)" @submit="submit" />
    </AppLayout>
</template>
