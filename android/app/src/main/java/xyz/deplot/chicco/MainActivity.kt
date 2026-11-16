package xyz.deplot.chicco

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.hilt.navigation.compose.hiltViewModel
import dagger.hilt.android.AndroidEntryPoint
import xyz.deplot.chicco.ui.auth.SessionViewModel
import xyz.deplot.chicco.ui.auth.TokenEntryScreen
import xyz.deplot.chicco.ui.navigation.ChiccoMainScaffold
import xyz.deplot.chicco.ui.theme.ChiccoTheme

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            ChiccoTheme {
                ChiccoApp()
            }
        }
    }
}

@Composable
fun ChiccoApp(sessionViewModel: SessionViewModel = hiltViewModel()) {
    val isLoggedIn by sessionViewModel.isLoggedIn.collectAsState()

    if (isLoggedIn) {
        ChiccoMainScaffold(onLogout = sessionViewModel::logout)
    } else {
        TokenEntryScreen(onTokenSaved = sessionViewModel::onLoginSuccess)
    }
}
