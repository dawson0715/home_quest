import { describe, expect, it, vi } from 'vitest';
import { page } from 'vitest/browser';
import { render } from 'vitest-browser-svelte';
import { createRawSnippet } from 'svelte';
import Button from './Button.svelte';

const label = (text: string) => createRawSnippet(() => ({ render: () => `<span>${text}</span>` }));

describe('Button', () => {
  it('calls onclick', async () => {
    const onclick = vi.fn();
    render(Button, { onclick, children: label('Salva') });

    await page.getByRole('button', { name: 'Salva' }).click();
    expect(onclick).toHaveBeenCalledOnce();
  });

  it('is disabled and busy while loading', async () => {
    render(Button, { loading: true, children: label('Salva') });

    const button = page.getByRole('button');
    await expect.element(button).toBeDisabled();
    await expect.element(button).toHaveAttribute('aria-busy', 'true');
  });
});
