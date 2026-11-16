package xyz.deplot.chicco.ui.components

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp

/** A compact stat tile in a role container tone (primary/secondary/tertiary/custom). */
@Composable
fun StatCard(
    icon: ImageVector,
    label: String,
    value: String,
    containerColor: Color,
    contentColor: Color,
    modifier: Modifier = Modifier,
    subValue: String? = null,
) {
    Card(
        modifier = modifier.width(140.dp),
        colors = CardDefaults.cardColors(containerColor = containerColor, contentColor = contentColor),
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Icon(icon, contentDescription = null)
            Text(
                text = value,
                style = MaterialTheme.typography.titleLarge,
                modifier = Modifier.padding(top = 8.dp),
            )
            if (subValue != null) {
                Text(text = subValue, style = MaterialTheme.typography.bodySmall)
            }
            Text(text = label, style = MaterialTheme.typography.bodySmall)
        }
    }
}
