package xyz.deplot.chicco.ui.components

import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material3.FilledTonalIconButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp

/**
 * A quantity input combining a numeric keyboard text field with +/- stepper buttons, so the
 * value can be typed directly or nudged by [step].
 */
@Composable
fun NumberStepperField(
    label: String,
    value: Int,
    step: Int,
    unit: String,
    onValueChange: (Int) -> Unit,
    modifier: Modifier = Modifier,
    minValue: Int = 0,
    maxValue: Int = Int.MAX_VALUE,
) {
    Row(modifier = modifier, verticalAlignment = Alignment.CenterVertically) {
        FilledTonalIconButton(onClick = { onValueChange((value - step).coerceIn(minValue, maxValue)) }) {
            Icon(Icons.Filled.Remove, contentDescription = "Diminuisci $label")
        }
        Spacer(modifier = Modifier.width(12.dp))
        var text by remember(value) { mutableStateOf(value.toString()) }
        OutlinedTextField(
            value = text,
            onValueChange = { input ->
                val digits = input.filter { it.isDigit() }
                text = digits
                digits.toIntOrNull()?.let { onValueChange(it.coerceIn(minValue, maxValue)) }
            },
            modifier = Modifier.widthIn(min = 96.dp),
            singleLine = true,
            textStyle = MaterialTheme.typography.titleMedium,
            suffix = { Text(unit) },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
        )
        Spacer(modifier = Modifier.width(12.dp))
        FilledTonalIconButton(onClick = { onValueChange((value + step).coerceIn(minValue, maxValue)) }) {
            Icon(Icons.Filled.Add, contentDescription = "Aumenta $label")
        }
    }
}
