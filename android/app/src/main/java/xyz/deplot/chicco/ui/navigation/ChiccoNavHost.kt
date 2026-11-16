package xyz.deplot.chicco.ui.navigation

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.BabyChangingStation
import androidx.compose.material.icons.filled.MonitorWeight
import androidx.compose.material.icons.filled.Restaurant
import androidx.compose.material.icons.filled.WaterDrop
import androidx.compose.material3.CenterAlignedTopAppBar
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ListItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import xyz.deplot.chicco.ui.liste.ChecklistScreen
import xyz.deplot.chicco.ui.oggi.OggiScreen
import xyz.deplot.chicco.ui.pannolini.PannoliniScreen
import xyz.deplot.chicco.ui.peso.PesoScreen
import xyz.deplot.chicco.ui.poppate.PoppateScreen
import xyz.deplot.chicco.ui.tiraggio.TiraggioScreen

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ChiccoMainScaffold(onLogout: () -> Unit) {
    val navController = rememberNavController()
    val backStackEntry by navController.currentBackStackEntryAsState()
    val currentDestination = ChiccoDestination.entries
        .firstOrNull { destination -> backStackEntry?.destination?.hierarchy?.any { it.route == destination.route } == true }
    var showQuickAddSheet by remember { mutableStateOf(false) }
    val topBarContent = remember { mutableStateOf<(@Composable () -> Unit)?>(null) }

    CompositionLocalProvider(LocalTopBarContent provides topBarContent) {
        Scaffold(
            topBar = {
                CenterAlignedTopAppBar(
                    title = {
                        topBarContent.value?.invoke()
                            ?: Text(currentDestination?.label ?: ChiccoDestination.OGGI.label)
                    },
                    colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                        titleContentColor = MaterialTheme.colorScheme.onPrimaryContainer,
                        actionIconContentColor = MaterialTheme.colorScheme.onPrimaryContainer,
                    ),
                    actions = {
                        IconButton(onClick = onLogout) {
                            Icon(Icons.AutoMirrored.Filled.Logout, contentDescription = "Esci")
                        }
                    }
                )
            },
            bottomBar = { ChiccoBottomBar(navController) },
            floatingActionButton = {
                if (currentDestination == ChiccoDestination.OGGI) {
                    FloatingActionButton(
                        onClick = { showQuickAddSheet = true },
                        shape = MaterialTheme.shapes.large,
                    ) {
                        Icon(Icons.Filled.Add, contentDescription = "Aggiungi rapido")
                    }
                }
            },
        ) { innerPadding ->
            NavHost(
                navController = navController,
                startDestination = ChiccoDestination.OGGI.route,
                modifier = Modifier.padding(innerPadding),
            ) {
                composable(ChiccoDestination.OGGI.route) { OggiScreen() }
                composable(ChiccoDestination.POPPATE.route) { PoppateScreen() }
                composable(ChiccoDestination.TIRAGGIO.route) { TiraggioScreen() }
                composable(ChiccoDestination.PESO.route) { PesoScreen() }
                composable(ChiccoDestination.PANNOLINI.route) { PannoliniScreen() }
                composable(ChiccoDestination.LISTE.route) { ChecklistScreen() }
            }
        }
    }

    if (showQuickAddSheet) {
        QuickAddSheet(
            onDismiss = { showQuickAddSheet = false },
            onDestinationSelected = { destination ->
                showQuickAddSheet = false
                navController.navigate(destination.route) {
                    popUpTo(navController.graph.findStartDestination().id) { saveState = true }
                    launchSingleTop = true
                    restoreState = true
                }
            },
        )
    }
}

private data class QuickAddOption(
    val destination: ChiccoDestination,
    val label: String,
    val icon: androidx.compose.ui.graphics.vector.ImageVector,
)

private val QUICK_ADD_OPTIONS = listOf(
    QuickAddOption(ChiccoDestination.POPPATE, "Nuova poppata", Icons.Filled.Restaurant),
    QuickAddOption(ChiccoDestination.TIRAGGIO, "Nuovo tiraggio", Icons.Filled.WaterDrop),
    QuickAddOption(ChiccoDestination.PESO, "Nuova pesata", Icons.Filled.MonitorWeight),
    QuickAddOption(ChiccoDestination.PANNOLINI, "Nuovo pannolino", Icons.Filled.BabyChangingStation),
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun QuickAddSheet(
    onDismiss: () -> Unit,
    onDestinationSelected: (ChiccoDestination) -> Unit,
) {
    val sheetState = rememberModalBottomSheetState()
    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheetState) {
        Column {
            QUICK_ADD_OPTIONS.forEach { option ->
                ListItem(
                    headlineContent = { Text(option.label) },
                    leadingContent = { Icon(option.icon, contentDescription = null) },
                    modifier = Modifier.clickable { onDestinationSelected(option.destination) },
                )
            }
        }
    }
}

@Composable
private fun ChiccoBottomBar(navController: androidx.navigation.NavHostController) {
    val backStackEntry by navController.currentBackStackEntryAsState()
    val currentDestination = backStackEntry?.destination

    NavigationBar {
        ChiccoDestination.entries.forEach { destination ->
            val selected = currentDestination?.hierarchy?.any { it.route == destination.route } == true
            NavigationBarItem(
                selected = selected,
                onClick = {
                    navController.navigate(destination.route) {
                        popUpTo(navController.graph.findStartDestination().id) {
                            saveState = true
                        }
                        launchSingleTop = true
                        restoreState = true
                    }
                },
                icon = { Icon(destination.icon, contentDescription = destination.label) },
                label = { Text(destination.label) },
            )
        }
    }
}
