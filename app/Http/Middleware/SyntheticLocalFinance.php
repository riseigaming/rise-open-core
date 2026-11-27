<?php
namespace App\Http\Middleware;
use Closure;
use App\Services\Finance\PrelaunchFinanceWriteGate;
class SyntheticLocalFinance {public function handle($request,Closure $next){app(PrelaunchFinanceWriteGate::class)->assertWritable();return $next($request);}}
