package xyz.deplot.chicco.di

import dagger.Binds
import dagger.Module
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton
import xyz.deplot.chicco.data.auth.EncryptedTokenStorage
import xyz.deplot.chicco.data.auth.TokenStorage
import xyz.deplot.chicco.data.repository.AuthRepository
import xyz.deplot.chicco.data.repository.AuthRepositoryImpl
import xyz.deplot.chicco.data.repository.ChecklistRepository
import xyz.deplot.chicco.data.repository.DiaperRepository
import xyz.deplot.chicco.data.repository.FeedingRepository
import xyz.deplot.chicco.data.repository.PumpingRepository
import xyz.deplot.chicco.data.repository.TodaySummaryRepository
import xyz.deplot.chicco.data.repository.WeightRepository
import xyz.deplot.chicco.data.repository.remote.ChecklistRepositoryImpl
import xyz.deplot.chicco.data.repository.remote.DiaperRepositoryImpl
import xyz.deplot.chicco.data.repository.remote.FeedingRepositoryImpl
import xyz.deplot.chicco.data.repository.remote.PumpingRepositoryImpl
import xyz.deplot.chicco.data.repository.remote.TodaySummaryRepositoryImpl
import xyz.deplot.chicco.data.repository.remote.WeightRepositoryImpl

// Bound to the real Retrofit-backed repositories — chicco.deplot.xyz is live
// (over plain HTTP for now, see NetworkModule). The Mock* implementations
// backed by FakeChiccoBackend remain available under data/repository/mock/
// if the real backend becomes unreachable again; swapping back is a one-line
// @Binds change per repository.
@Module
@InstallIn(SingletonComponent::class)
abstract class RepositoryModule {

    @Binds
    @Singleton
    abstract fun bindTokenStorage(impl: EncryptedTokenStorage): TokenStorage

    @Binds
    @Singleton
    abstract fun bindAuthRepository(impl: AuthRepositoryImpl): AuthRepository

    @Binds
    @Singleton
    abstract fun bindTodaySummaryRepository(impl: TodaySummaryRepositoryImpl): TodaySummaryRepository

    @Binds
    @Singleton
    abstract fun bindFeedingRepository(impl: FeedingRepositoryImpl): FeedingRepository

    @Binds
    @Singleton
    abstract fun bindPumpingRepository(impl: PumpingRepositoryImpl): PumpingRepository

    @Binds
    @Singleton
    abstract fun bindWeightRepository(impl: WeightRepositoryImpl): WeightRepository

    @Binds
    @Singleton
    abstract fun bindDiaperRepository(impl: DiaperRepositoryImpl): DiaperRepository

    @Binds
    @Singleton
    abstract fun bindChecklistRepository(impl: ChecklistRepositoryImpl): ChecklistRepository
}
