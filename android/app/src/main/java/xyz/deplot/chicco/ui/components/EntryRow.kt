package xyz.deplot.chicco.ui.components

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp

/**
 * Compact, background-less list row: icon badge + headline/supporting content + optional
 * trailing text. Used instead of Material3's [androidx.compose.material3.ListItem] because that
 * component's built-in padding makes rows taller than wanted here and always paints a
 * container color — this gives full control over both.
 */
@Composable
fun EntryRow(
    icon: ImageVector,
    iconContainerColor: Color,
    iconContentColor: Color,
    headline: String,
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    trailing: String? = null,
    supportingContent: @Composable (ColumnScope.() -> Unit)? = null,
    // Overrides [trailing] when set — for rows that need more than a single trailing line
    // (e.g. a bigger primary value with a smaller gray line underneath).
    trailingContent: @Composable (ColumnScope.() -> Unit)? = null,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .let { if (onClick != null) it.clickable(onClick = onClick) else it }
            .padding(horizontal = 4.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        IconBadge(icon = icon, containerColor = iconContainerColor, contentColor = iconContentColor)
        Spacer(modifier = Modifier.width(16.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(text = headline, style = MaterialTheme.typography.bodyLarge)
            supportingContent?.invoke(this)
        }
        if (trailingContent != null) {
            Spacer(modifier = Modifier.width(8.dp))
            Column(horizontalAlignment = Alignment.End) { trailingContent() }
        } else if (trailing != null) {
            Spacer(modifier = Modifier.width(8.dp))
            Text(
                text = trailing,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}
