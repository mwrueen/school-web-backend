<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Student;

class PerformanceBenchmark extends Command
{
    protected $signature = 'performance:benchmark {--iterations=10}';
    protected $description = 'Run performance benchmarks for the application';

    public function handle(): void
    {
        $iterations = (int) $this->option('iterations');
        
        $this->info("Running performance benchmarks with {$iterations} iterations...");
        $this->newLine();
        
        $results = [
            'database_queries' => $this->benchmarkDatabaseQueries($iterations),
            'cache_operations' => $this->benchmarkCacheOperations($iterations),
            'api_endpoints' => $this->benchmarkApiEndpoints($iterations),
            'memory_usage' => $this->benchmarkMemoryUsage(),
        ];
        
        $this->displayResults($results);
    }

    private function benchmarkDatabaseQueries(int $iterations): array
    {
        $this->info('Benchmarking database queries...');
        
        $queries = [
            'simple_select' => fn() => Announcement::where('is_public', true)->count(),
            'complex_join' => fn() => Student::with(['classes'])->where('status', 'active')->limit(10)->get(),
            'aggregation' => fn() => DB::table('announcements')
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->get(),
            'indexed_search' => fn() => Event::where('type', 'academic')
                ->where('event_date', '>', now())
                ->orderBy('event_date')
                ->limit(5)
                ->get(),
        ];
        
        $results = [];
        
        foreach ($queries as $name => $query) {
            $times = [];
            
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                $query();
                $end = microtime(true);
                $times[] = ($end - $start) * 1000; // Convert to milliseconds
            }
            
            $results[$name] = [
                'avg' => round(array_sum($times) / count($times), 2),
                'min' => round(min($times), 2),
                'max' => round(max($times), 2),
            ];
        }
        
        return $results;
    }

    private function benchmarkCacheOperations(int $iterations): array
    {
        $this->info('Benchmarking cache operations...');
        
        $operations = [
            'cache_put' => function () {
                Cache::put('benchmark_key', ['data' => 'test'], 60);
            },
            'cache_get' => function () {
                Cache::get('benchmark_key');
            },
            'cache_remember' => function () {
                Cache::remember('benchmark_remember', 60, function () {
                    return ['computed' => 'data'];
                });
            },
        ];
        
        $results = [];
        
        foreach ($operations as $name => $operation) {
            $times = [];
            
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                $operation();
                $end = microtime(true);
                $times[] = ($end - $start) * 1000;
            }
            
            $results[$name] = [
                'avg' => round(array_sum($times) / count($times), 2),
                'min' => round(min($times), 2),
                'max' => round(max($times), 2),
            ];
        }
        
        return $results;
    }

    private function benchmarkApiEndpoints(int $iterations): array
    {
        $this->info('Benchmarking API endpoints...');
        
        $baseUrl = config('app.url');
        $endpoints = [
            '/api/public/home',
            '/api/public/academics/curriculum',
            '/api/public/admissions/process',
            '/api/public/events',
        ];
        
        $results = [];
        
        foreach ($endpoints as $endpoint) {
            $times = [];
            
            for ($i = 0; $i < min($iterations, 5); $i++) { // Limit API calls
                $start = microtime(true);
                
                try {
                    $response = Http::timeout(10)->get($baseUrl . $endpoint);
                    $end = microtime(true);
                    
                    if ($response->successful()) {
                        $times[] = ($end - $start) * 1000;
                    }
                } catch (\Exception $e) {
                    $this->warn("Failed to benchmark {$endpoint}: " . $e->getMessage());
                }
            }
            
            if (!empty($times)) {
                $results[$endpoint] = [
                    'avg' => round(array_sum($times) / count($times), 2),
                    'min' => round(min($times), 2),
                    'max' => round(max($times), 2),
                ];
            }
        }
        
        return $results;
    }

    private function benchmarkMemoryUsage(): array
    {
        $this->info('Benchmarking memory usage...');
        
        $initialMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        // Simulate heavy operations
        $data = [];
        for ($i = 0; $i < 1000; $i++) {
            $data[] = [
                'id' => $i,
                'data' => str_repeat('x', 100),
                'timestamp' => now(),
            ];
        }
        
        $afterOperationMemory = memory_get_usage(true);
        $newPeakMemory = memory_get_peak_usage(true);
        
        unset($data); // Clean up
        
        return [
            'initial_mb' => round($initialMemory / 1024 / 1024, 2),
            'after_operation_mb' => round($afterOperationMemory / 1024 / 1024, 2),
            'peak_mb' => round($newPeakMemory / 1024 / 1024, 2),
            'increase_mb' => round(($afterOperationMemory - $initialMemory) / 1024 / 1024, 2),
        ];
    }

    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('=== PERFORMANCE BENCHMARK RESULTS ===');
        $this->newLine();
        
        // Database Queries
        $this->info('Database Queries (ms):');
        foreach ($results['database_queries'] as $query => $metrics) {
            $this->line("  {$query}: avg={$metrics['avg']}, min={$metrics['min']}, max={$metrics['max']}");
        }
        $this->newLine();
        
        // Cache Operations
        $this->info('Cache Operations (ms):');
        foreach ($results['cache_operations'] as $operation => $metrics) {
            $this->line("  {$operation}: avg={$metrics['avg']}, min={$metrics['min']}, max={$metrics['max']}");
        }
        $this->newLine();
        
        // API Endpoints
        if (!empty($results['api_endpoints'])) {
            $this->info('API Endpoints (ms):');
            foreach ($results['api_endpoints'] as $endpoint => $metrics) {
                $this->line("  {$endpoint}: avg={$metrics['avg']}, min={$metrics['min']}, max={$metrics['max']}");
            }
            $this->newLine();
        }
        
        // Memory Usage
        $this->info('Memory Usage:');
        $memory = $results['memory_usage'];
        $this->line("  Initial: {$memory['initial_mb']} MB");
        $this->line("  After Operation: {$memory['after_operation_mb']} MB");
        $this->line("  Peak: {$memory['peak_mb']} MB");
        $this->line("  Increase: {$memory['increase_mb']} MB");
        
        $this->newLine();
        $this->info('Benchmark completed!');
    }
}