package xyz.deplot.chicco.data.model

fun FeedingType.label(): String = when (this) {
    FeedingType.FORMULA -> "Formula"
    FeedingType.BREAST_MILK -> "Latte Materno"
    FeedingType.BREAST -> "Seno"
}

fun Side.label(): String = when (this) {
    Side.LEFT -> "Sinistro"
    Side.RIGHT -> "Destro"
    Side.BOTH -> "Entrambi"
}

fun DiaperType.label(): String = when (this) {
    DiaperType.PEE -> "Pipì"
    DiaperType.POOP -> "Cacca"
    DiaperType.MIXED -> "Misto"
}

fun ChecklistList.label(): String = when (this) {
    ChecklistList.BAG -> "Borsa"
    ChecklistList.SHOPPING -> "Spesa"
    ChecklistList.TODO -> "Da fare"
}
