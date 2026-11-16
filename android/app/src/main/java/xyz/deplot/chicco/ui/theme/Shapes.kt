package xyz.deplot.chicco.ui.theme

import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Shapes
import androidx.compose.ui.unit.dp

// More generous, "expressive" M3 corner radii than the library defaults —
// applies app-wide to Card/Button/Chip/Sheet/Dialog via MaterialTheme.
val ChiccoShapes = Shapes(
    extraSmall = RoundedCornerShape(8.dp),
    small = RoundedCornerShape(12.dp),
    medium = RoundedCornerShape(16.dp),
    large = RoundedCornerShape(24.dp),
    extraLarge = RoundedCornerShape(32.dp),
)
