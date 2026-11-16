<script lang="ts">
  import { t } from '#lib/i18n/index.ts';
  import { avatarEmoji } from '#lib/utils/avatars.ts';

  interface Props {
    avatar: string;
    color: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    /** Decorative when the name is already next to it. */
    decorative?: boolean;
  }

  let { avatar, color, size = 'md', decorative = false }: Props = $props();

  const sizes = {
    sm: 'size-10 text-xl',
    md: 'size-14 text-3xl',
    lg: 'size-20 text-5xl',
    xl: 'size-28 text-7xl'
  };
</script>

<span
  class="inline-grid shrink-0 place-items-center rounded-full {sizes[size]}"
  style:background-color="{color}33"
  style:box-shadow="inset 0 0 0 3px {color}"
  role={decorative ? undefined : 'img'}
  aria-hidden={decorative ? 'true' : undefined}
  aria-label={decorative ? undefined : t(`avatar.${avatar}`)}
>
  {avatarEmoji(avatar)}
</span>
