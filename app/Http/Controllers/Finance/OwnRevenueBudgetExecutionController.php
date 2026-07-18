<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Execution\AuthorizeExpensePaymentByBudgetOffice;
use App\Actions\Finance\OwnRevenue\Execution\AuthorizeExpensePaymentByFinance;
use App\Actions\Finance\OwnRevenue\Execution\AuthorizeExpenseRequirementException;
use App\Actions\Finance\OwnRevenue\Execution\CancelExpenseDossier;
use App\Actions\Finance\OwnRevenue\Execution\CompleteExpenseRequirement;
use App\Actions\Finance\OwnRevenue\Execution\ConfirmExpenseSufficiency;
use App\Actions\Finance\OwnRevenue\Execution\CreateExpenseRequirementRule;
use App\Actions\Finance\OwnRevenue\Execution\CreateOwnRevenueExpenseDossier;
use App\Actions\Finance\OwnRevenue\Execution\DeactivateExpenseRequirementRule;
use App\Actions\Finance\OwnRevenue\Execution\InitializeOwnRevenueModifiedBudget;
use App\Actions\Finance\OwnRevenue\Execution\MarkExpenseDossierPaid;
use App\Actions\Finance\OwnRevenue\Execution\RejectExpenseDossier;
use App\Actions\Finance\OwnRevenue\Execution\RequestExpensePayment;
use App\Actions\Finance\OwnRevenue\Execution\RequestExpenseSufficiency;
use App\Actions\Finance\OwnRevenue\Execution\StartExpensePurchase;
use App\Actions\Finance\OwnRevenue\Execution\StoreOwnRevenueBudgetModification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Execution\AuthorizeExpensePaymentByBudgetOfficeRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\AuthorizeExpensePaymentByFinanceRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\AuthorizeExpenseRequirementExceptionRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\CancelExpenseDossierRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\CompleteExpenseRequirementRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\DeactivateExpenseRequirementRuleRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\MarkExpenseDossierPaidRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\RejectExpenseDossierRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\RequestExpensePaymentRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\StartExpensePurchaseRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\StoreExpenseRequirementRuleRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\StoreOwnRevenueBudgetModificationRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\StoreOwnRevenueExpenseDossierRequest;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierRequirement;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirementRule;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueExecutionViewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueBudgetExecutionController extends Controller
{
    public function __construct(
        private readonly InitializeOwnRevenueModifiedBudget $initialize,
        private readonly OwnRevenueExecutionViewData $viewData,
    ) {}

    public function show(Request $request, OwnRevenueBudget $budget): Response
    {
        Gate::authorize('view', $budget);
        $initialBudget = $budget->initialBudgets()->latest('authorized_at')->first();
        abort_if($initialBudget === null, 404);
        $this->initialize->handle($initialBudget);

        return Inertia::render('finance/own-revenue/execution/show', $this->viewData->forBudget($budget->fresh()));
    }

    public function store(
        StoreOwnRevenueBudgetModificationRequest $request,
        OwnRevenueBudget $budget,
        StoreOwnRevenueBudgetModification $store,
    ): RedirectResponse {
        $store->handle($budget, $request->user(), $request->validated());

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'La modificación presupuestal quedó registrada.');
    }

    public function storeExpenseDossier(
        StoreOwnRevenueExpenseDossierRequest $request,
        OwnRevenueBudget $budget,
        CreateOwnRevenueExpenseDossier $create,
    ): RedirectResponse {
        $data = $request->validated();
        $line = OwnRevenueModifiedBudgetLine::query()->findOrFail($data['own_revenue_modified_budget_line_id']);
        $create->handle($budget, $line, $request->user(), $data);

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'El expediente de gasto quedó guardado como borrador.');
    }

    public function requestSufficiency(
        Request $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        RequestExpenseSufficiency $requestSufficiency,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        $requestSufficiency->handle($expenseDossier, $request->user());

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'La suficiencia quedó solicitada y el importe fue reservado.');
    }

    public function confirmSufficiency(
        Request $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        ConfirmExpenseSufficiency $confirmSufficiency,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        $confirmSufficiency->handle($expenseDossier, $request->user());

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'La suficiencia quedó confirmada y el importe fue comprometido.');
    }

    public function startPurchase(
        StartExpensePurchaseRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        StartExpensePurchase $startPurchase,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        $startPurchase->handle($expenseDossier, $request->user(), $request->validated('purchase_reference'));

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'La compra o contratación quedó registrada como iniciada.');
    }

    public function requestPayment(
        RequestExpensePaymentRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        RequestExpensePayment $requestPayment,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        $requestPayment->handle(
            $expenseDossier,
            $request->user(),
            $request->validated('payment_request_reference'),
            $request->file('documents', []),
        );

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'La solicitud de pago y sus documentos quedaron registrados.');
    }

    public function authorizeByFinance(
        AuthorizeExpensePaymentByFinanceRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        AuthorizeExpensePaymentByFinance $authorize,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        $authorize->handle($expenseDossier, $request->user(), $request->validated('finance_authorization_reference'));

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'La autorización de Finanzas quedó registrada.');
    }

    public function authorizeByBudgetOffice(
        AuthorizeExpensePaymentByBudgetOfficeRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        AuthorizeExpensePaymentByBudgetOffice $authorize,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        $authorize->handle($expenseDossier, $request->user(), $request->validated('budget_office_authorization_reference'));

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'La autorización de Presupuesto o Pagaduría quedó registrada.');
    }

    public function markPaid(
        MarkExpenseDossierPaidRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        MarkExpenseDossierPaid $markPaid,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        $markPaid->handle($expenseDossier, $request->user(), $request->validated('payment_reference'));

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'El pago quedó registrado y el saldo se actualizó.');
    }

    public function cancelExpenseDossier(
        CancelExpenseDossierRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        CancelExpenseDossier $cancel,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        $cancel->handle($expenseDossier, $request->user(), $request->validated('reason'));

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'El expediente quedó cancelado y el saldo fue liberado.');
    }

    public function rejectExpenseDossier(
        RejectExpenseDossierRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        RejectExpenseDossier $reject,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        $reject->handle($expenseDossier, $request->user(), $request->validated('reason'));

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'El expediente quedó rechazado y el saldo fue liberado.');
    }

    public function completeExpenseRequirement(
        CompleteExpenseRequirementRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        OwnRevenueExpenseDossierRequirement $requirement,
        CompleteExpenseRequirement $complete,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        abort_unless($requirement->own_revenue_expense_dossier_id === $expenseDossier->id, 404);
        $complete->handle(
            $requirement,
            $request->user(),
            (string) $request->validated('notes', ''),
            $request->file('evidence'),
        );

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'El requisito quedó atendido.');
    }

    public function exceptExpenseRequirement(
        AuthorizeExpenseRequirementExceptionRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        OwnRevenueExpenseDossierRequirement $requirement,
        AuthorizeExpenseRequirementException $authorizeException,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        abort_unless($requirement->own_revenue_expense_dossier_id === $expenseDossier->id, 404);
        $authorizeException->handle(
            $requirement,
            $request->user(),
            $request->validated('exception_reason'),
            $request->file('evidence'),
        );

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'La excepción quedó autorizada con su evidencia.');
    }

    public function storeExpenseRequirementRule(
        StoreExpenseRequirementRuleRequest $request,
        OwnRevenueBudget $budget,
        CreateExpenseRequirementRule $create,
    ): RedirectResponse {
        $create->handle($budget, $request->user(), $request->validated());

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'El requisito quedó agregado a la lista de verificación.');
    }

    public function deactivateExpenseRequirementRule(
        DeactivateExpenseRequirementRuleRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseRequirementRule $expenseRequirementRule,
        DeactivateExpenseRequirementRule $deactivate,
    ): RedirectResponse {
        abort_unless($expenseRequirementRule->own_revenue_budget_id === $budget->id, 404);
        $deactivate->handle($expenseRequirementRule, $request->user());

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'El requisito dejó de aplicarse a nuevos avances.');
    }
}
