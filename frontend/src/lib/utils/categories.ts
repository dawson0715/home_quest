import type { ShoppingCategory } from '../api/types';
import { t } from '../i18n';

export function categoryName(category: ShoppingCategory | undefined): string {
  if (!category) return t('shopping_categories.altro');
  return category.code ? t(`shopping_categories.${category.code}`) : (category.name ?? '');
}

/** "1,25 kg", "6 pz", "q.b." */
export function formatQuantity(quantity: number | null, unit: string | null): string {
  if (unit === 'qb') return t('units.qb');
  if (quantity === null) return '';
  const n = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 2 }).format(quantity);
  return unit ? `${n} ${t(`units.${unit}`)}` : n;
}
