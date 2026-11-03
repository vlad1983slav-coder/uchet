<?php

declare(strict_types=1);

namespace Uchet\Services;

use Uchet\Core\Auth;
use Uchet\Core\Database;
use Uchet\Core\HttpException;
use Uchet\Core\NotFoundException;
use Uchet\Repositories\DocumentRepository;
use Uchet\Repositories\InventoryRepository;
use Uchet\Repositories\IssueRepository;
use Uchet\Repositories\MovementRepository;
use Uchet\Repositories\ReturnRepository;
use Uchet\Repositories\ReceiptRepository;
use Uchet\Repositories\MoveRepository;

/**
 * Сервис документов «как в 1С»:
 *
 *  - createDocument/updateDocument — черновик (нумерация ПН-000001, аудит);
 *  - post()   — проведение: транзакция БД, блокировка шапки FOR UPDATE, запись в регистры
 *               остатков (партии), движений и взаиморасчётов, статус draft→posted;
 *  - unpost() — распроведение: обратные движения, удаление регистров документа, posted→draft;
 *  - закрытый период: документы с датой <= settings.closed_period_date нельзя
 *    провести/распровести/изменить;
 *  - при ошибке проведения транзакция откатывается, документ получает статус 'error' (красный),
 *    сообщение — в исключении (422) и в audit_log.
 *
 * Типы: receipts (ПН), issues (РН), moves (ПМ), inventories (ИНВ), returns (ВП/ВК).
 */
final class DocumentService
{
    public const TYPE_RECEIPTS = 'receipts';
    public const TYPE_ISSUES = 'issues';
    public const TYPE_MOVES = 'moves';
    public const TYPE_INVENTORIES = 'inventories';
    public const TYPE_RETURNS = 'returns';

    public const TYPES = [
        self::TYPE_RECEIPTS,
        self::TYPE_ISSUES,
        self::TYPE_MOVES,
        self::TYPE_INVENTORIES,
        self::TYPE_RETURNS,
    ];

    /** source_type партий, создаваемых данным типом документа (для отмены проведения) */
    private const PARTY_SOURCE_BY_TYPE = [
        self::TYPE_RECEIPTS => ['receipt'],
        self::TYPE_RETURNS => ['return_customer'],
        self::TYPE_INVENTORIES => ['inventory_surplus'],
        self::TYPE_ISSUES => [],
        self::TYPE_MOVES => [],
    ];

    public function __construct(
        private readonly ReceiptRepository $receipts,
        private readonly IssueRepository $issues,
        private readonly MoveRepository $moves,
        private readonly InventoryRepository $inventories,
        private readonly ReturnRepository $returns,
        private readonly StockService $stock,
        private readonly SettlementService $settlements,
        private readonly MovementRepository $movements,
        private readonly NumberingService $numbering,
    ) {}

    public static function make(): self
    {
        $movements = new MovementRepository();
        $stock = new StockService(movements: $movements);

        return new self(
            new ReceiptRepository(),
            new IssueRepository(),
            new MoveRepository(),
            new InventoryRepository(),
            new ReturnRepository(),
            $stock,
            new SettlementService(),
            $movements,
            new NumberingService(),
        );
    }

    public static function assertType(string $type): void
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("Неизвестный тип документа: {$type}");
        }
    }

    public function repoFor(string $type): DocumentRepository
    {
        self::assertType($type);

        return match ($type) {
            self::TYPE_RECEIPTS => $this->receipts,
            self::TYPE_ISSUES => $this->issues,
            self::TYPE_MOVES => $this->moves,
            self::TYPE_INVENTORIES => $this->inventories,
            self::TYPE_RETURNS => $this->returns,
        };
    }

    public function tableFor(string $type): string
    {
        return $this->repoFor($type)->tableName();
    }

    public function nextNumber(string $type, string $docDate, ?string $direction = null): string
    {
        self::assertType($type);
        $prefix = SettingsService::prefixFor($type, $direction);

        return $this->numbering->nextNumber($prefix, $docDate);
    }

    // ------------------------------------------------------------------ создание/изменение

    /**
     * Создать черновик документа: номер, created_by, шапка + ТЧ, итоги, аудит — в одной транзакции.
     *
     * @param array<string, mixed> $data  поля шапки (number/prefix/status создаются здесь)
     * @param array<int, array<string, mixed>> $lines строки ТЧ
     */
    public function createDocument(string $type, array $data, array $lines): int
    {
        $repo = $this->repoFor($type);
        Database::beginTransaction();
        try {
            $direction = isset($data['direction']) ? (string) $data['direction'] : null;
            $prefix = SettingsService::prefixFor($type, $direction);
            $data['prefix'] = $prefix;
            $data['number'] = $this->numbering->nextNumber($prefix, (string) $data['doc_date']);
            $data['status'] = 'draft';
            $data['created_by'] = Auth::id();
            unset($data['id'], $data['posted_at'], $data['posted_by'], $data['total_quantity'], $data['total_amount'], $data['created_at'], $data['updated_at'], $data['deleted_at']);

            $header = $repo->create($data);
            $id = (int) $header->id;
            $repo->replaceLines($id, $lines);
            $repo->recalcTotals($id);

            AuditService::log($repo->tableName(), $id, 'create', null, ['number' => $data['number'], 'doc_date' => $data['doc_date']]);
            Database::commit();

            return $id;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e instanceof HttpException || $e instanceof NotFoundException
                ? $e
                : new HttpException(422, 'Ошибка создания документа: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Обновить черновик (проведённый документ редактировать нельзя — сначала распроведите).
     *
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $lines
     */
    public function updateDocument(string $type, int $id, array $data, array $lines): void
    {
        $repo = $this->repoFor($type);
        Database::beginTransaction();
        try {
            $header = $this->lockHeader($repo, $id);
            if ($header->status === 'posted') {
                throw new HttpException(422, "Документ {$header->number} проведён — сначала распроведите его");
            }
            $this->checkClosedPeriod((string) ($data['doc_date'] ?? $header->doc_date));

            $old = $header->toArray();
            unset($data['id'], $data['number'], $data['prefix'], $data['status'], $data['posted_at'], $data['posted_by'], $data['created_by'], $data['created_at'], $data['updated_at'], $data['deleted_at']);
            $repo->update($id, $data);
            $repo->replaceLines($id, $lines);
            $repo->recalcTotals($id);

            AuditService::log($repo->tableName(), $id, 'update', $old, $data + ['lines_count' => count($lines)]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e instanceof HttpException || $e instanceof NotFoundException
                ? $e
                : new HttpException(422, 'Ошибка сохранения документа: ' . $e->getMessage(), $e);
        }
    }

    // ------------------------------------------------------------------ проведение

    /**
     * Провести документ (draft/error → posted). Вся запись регистров — в одной транзакции.
     *
     * @throws HttpException(422) при любой ошибке проведения (недостаток товара, закрытый период...)
     */
    public function post(string $type, int $id): void
    {
        $repo = $this->repoFor($type);
        Database::beginTransaction();
        try {
            $header = $this->lockHeader($repo, $id);
            if ($header->status === 'posted') {
                throw new HttpException(422, "Документ {$header->number} уже проведён");
            }
            $this->checkClosedPeriod((string) $header->doc_date);

            $lines = $repo->lines($id);
            if ($lines === []) {
                throw new HttpException(422, "В документе {$header->number} нет строк табличной части");
            }

            $oldStatus = (string) $header->status;
            match ($type) {
                self::TYPE_RECEIPTS => $this->postReceipt($header, $lines),
                self::TYPE_ISSUES => $this->postIssue($header, $lines),
                self::TYPE_MOVES => $this->postMove($header, $lines),
                self::TYPE_INVENTORIES => $this->postInventory($header, $lines),
                self::TYPE_RETURNS => $this->postReturn($header, $lines),
            };

            $repo->recalcTotals($id);
            $repo->execute(
                'UPDATE ' . $repo->tableName() . " SET status = 'posted', posted_at = NOW(), posted_by = ?, updated_at = NOW() WHERE id = ?",
                [Auth::id(), $id]
            );
            AuditService::log($repo->tableName(), $id, 'post', ['status' => $oldStatus], ['status' => 'posted', 'posted_by' => Auth::id()]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->markError($repo, $id, $e);
            throw $e instanceof HttpException
                ? $e
                : new HttpException(422, 'Ошибка проведения: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Распровести документ (posted → draft): обратные движения по регистрам,
     * удаление движений и взаиморасчётов документа — в одной транзакции.
     */
    public function unpost(string $type, int $id): void
    {
        $repo = $this->repoFor($type);
        Database::beginTransaction();
        try {
            $header = $this->lockHeader($repo, $id);
            if ($header->status !== 'posted') {
                throw new HttpException(422, "Документ {$header->number} не проведён");
            }
            $this->checkClosedPeriod((string) $header->doc_date);

            $movements = $this->movements->byDocument($type, $id);

            // блокируем все затронутые остатки до обратных проводок
            $pairs = [];
            foreach ($movements as $mv) {
                $pairs[(int) $mv['warehouse_id'] . ':' . (int) $mv['nomenclature_id']] = [(int) $mv['warehouse_id'], (int) $mv['nomenclature_id']];
            }
            foreach ($pairs as [$wh, $nom]) {
                $this->stock->stock()->lockRows($wh, $nom);
            }

            foreach ($movements as $mv) {
                $this->reverseMovement($type, $id, $mv);
            }
            $this->movements->deleteByDocument($type, $id);
            $this->settlements->deleteByDocument($type, $id);

            $repo->execute(
                'UPDATE ' . $repo->tableName() . " SET status = 'draft', posted_at = NULL, posted_by = NULL, updated_at = NOW() WHERE id = ?",
                [$id]
            );
            AuditService::log($repo->tableName(), $id, 'unpost', ['status' => 'posted'], ['status' => 'draft']);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e instanceof HttpException
                ? $e
                : new HttpException(422, 'Ошибка распроведения: ' . $e->getMessage(), $e);
        }
    }

    // ------------------------------------------------------------------ обработчики проведения

    /** ПН: создать партию на каждую строку, оприходовать, долг поставщику (−). */
    private function postReceipt(object $header, array $lines): void
    {
        $type = self::TYPE_RECEIPTS;
        $id = (int) $header->id;
        $warehouseId = (int) $header->warehouse_id;
        $total = 0.0;
        foreach ($lines as $line) {
            $qty = (float) $line->quantity;
            $price = (float) $line->price;
            if ($qty <= 0) {
                throw new HttpException(422, "Строка {$line->line_no}: количество должно быть > 0");
            }
            $partyId = $this->stock->createParty((int) $line->nomenclature_id, 'receipt', $id);
            $cost = round($qty * $price, 2);
            $this->stock->receive($warehouseId, (int) $line->nomenclature_id, $partyId, $qty, $cost, $type, $id, (string) $header->doc_date);
            $total += $cost;
        }
        $this->settlements->add((int) $header->counterparty_id, $type, $id, (string) $header->doc_date, -1 * round($total, 2));
    }

    /** РН: списать FIFO со склада, долг покупателя (+). */
    private function postIssue(object $header, array $lines): void
    {
        $type = self::TYPE_ISSUES;
        $id = (int) $header->id;
        $warehouseId = (int) $header->warehouse_id;
        $total = 0.0;
        foreach ($this->groupByNomenclature($lines) as $nomId => $agg) {
            $this->stock->writeOff($warehouseId, $nomId, $agg['qty'], $type, $id, (string) $header->doc_date, true);
        }
        foreach ($lines as $line) {
            $total += (float) ($line->amount ?? ((float) $line->quantity * (float) $line->price));
        }
        $this->settlements->add((int) $header->counterparty_id, $type, $id, (string) $header->doc_date, round($total, 2));
    }

    /** ПМ: списать со склада-источника (партии сохраняются), оприходовать на склад-получатель. */
    private function postMove(object $header, array $lines): void
    {
        $type = self::TYPE_MOVES;
        $id = (int) $header->id;
        $from = (int) $header->warehouse_from_id;
        $to = (int) $header->warehouse_to_id;
        if ($from === $to) {
            throw new HttpException(422, 'Склад-источник и склад-получатель совпадают');
        }
        foreach ($this->groupByNomenclature($lines) as $nomId => $agg) {
            $allocations = $this->stock->writeOff($from, $nomId, $agg['qty'], $type, $id, (string) $header->doc_date, true);
            foreach ($allocations as $allocation) {
                // партия сохраняется: тот же party_id на новом складе, себестоимость переносится
                $this->stock->receive($to, $nomId, $allocation['party_id'], $allocation['qty'], $allocation['amount'], $type, $id, (string) $header->doc_date);
            }
        }
    }

    /** ИНВ: излишек (fact > book) → новая партия по средней цене; недостача → списание FIFO. */
    private function postInventory(object $header, array $lines): void
    {
        $type = self::TYPE_INVENTORIES;
        $id = (int) $header->id;
        $warehouseId = (int) $header->warehouse_id;
        foreach ($lines as $line) {
            $nomId = (int) $line->nomenclature_id;
            $diff = (float) $line->fact_quantity - (float) $line->book_quantity;
            if (abs($diff) <= 0.0005) {
                continue;
            }
            if ($diff > 0) {
                $partyId = $this->stock->createParty($nomId, 'inventory_surplus', $id);
                $unitCost = $this->stock->avgCost($warehouseId, $nomId);
                if ($unitCost <= 0) {
                    $unitCost = $this->purchasePrice($nomId);
                }
                $this->stock->receive($warehouseId, $nomId, $partyId, $diff, round($diff * $unitCost, 2), $type, $id, (string) $header->doc_date);
            } else {
                // недостача списывается без жёсткой проверки остатка (факт и так меньше учёта)
                $this->stock->writeOff($warehouseId, $nomId, -$diff, $type, $id, (string) $header->doc_date, false);
            }
        }
    }

    /** ВП (to_supplier): списание FIFO + уменьшение нашего долга (+); ВК (from_customer): новая партия + (−). */
    private function postReturn(object $header, array $lines): void
    {
        $type = self::TYPE_RETURNS;
        $id = (int) $header->id;
        $warehouseId = (int) $header->warehouse_id;
        $direction = (string) $header->direction;
        $total = 0.0;
        foreach ($lines as $line) {
            $total += (float) ($line->amount ?? ((float) $line->quantity * (float) $line->price));
        }
        if ($direction === 'to_supplier') {
            foreach ($this->groupByNomenclature($lines) as $nomId => $agg) {
                $this->stock->writeOff($warehouseId, $nomId, $agg['qty'], $type, $id, (string) $header->doc_date, true);
            }
            $this->settlements->add((int) $header->counterparty_id, $type, $id, (string) $header->doc_date, round($total, 2));
        } else { // from_customer
            foreach ($lines as $line) {
                $qty = (float) $line->quantity;
                if ($qty <= 0) {
                    throw new HttpException(422, "Строка {$line->line_no}: количество должно быть > 0");
                }
                $partyId = $this->stock->createParty((int) $line->nomenclature_id, 'return_customer', $id);
                $this->stock->receive(
                    $warehouseId,
                    (int) $line->nomenclature_id,
                    $partyId,
                    $qty,
                    round($qty * (float) $line->price, 2),
                    $type,
                    $id,
                    (string) $header->doc_date
                );
            }
            $this->settlements->add((int) $header->counterparty_id, $type, $id, (string) $header->doc_date, -1 * round($total, 2));
        }
    }

    // ------------------------------------------------------------------ отмена проведения

    /**
     * Обратная проводка одного движения:
     *  - quantity_in  → убираем с остатка; если партию создал этот документ — удаляем партию;
     *  - quantity_out → возвращаем на остаток партии.
     *
     * @param array<string, mixed> $mv
     */
    private function reverseMovement(string $type, int $docId, array $mv): void
    {
        $warehouseId = (int) $mv['warehouse_id'];
        $nomId = (int) $mv['nomenclature_id'];
        $partyId = $mv['party_id'] !== null ? (int) $mv['party_id'] : null;

        if ((float) $mv['quantity_in'] > 0) {
            if ($partyId !== null && $this->partyCreatedByDocument($partyId, $type, $docId)) {
                $this->stock->stock()->deleteParty($partyId);
            } else {
                $this->stock->stock()->subtractQty($warehouseId, $nomId, $partyId, (float) $mv['quantity_in'], (float) $mv['amount_in']);
            }
        }
        if ((float) $mv['quantity_out'] > 0) {
            if ($partyId !== null) {
                $this->stock->stock()->addQty($warehouseId, $nomId, $partyId, (float) $mv['quantity_out'], (float) $mv['amount_out']);
            }
        }
    }

    private function partyCreatedByDocument(int $partyId, string $type, int $docId): bool
    {
        $expected = self::PARTY_SOURCE_BY_TYPE[$type] ?? [];
        if ($expected === []) {
            return false;
        }
        $party = $this->stock->stock()->party($partyId);
        if ($party === null) {
            return false;
        }

        return (int) $party['source_id'] === $docId && in_array((string) $party['source_type'], $expected, true);
    }

    // ------------------------------------------------------------------ вспомогательное

    /** Заблокировать шапку документа FOR UPDATE (внутри транзакции) и прочитать её. */
    private function lockHeader(DocumentRepository $repo, int $id): object
    {
        $rows = $repo->query('SELECT * FROM ' . $repo->tableName() . ' WHERE id = ? FOR UPDATE', [$id]);
        if ($rows === []) {
            throw new NotFoundException("Документ #{$id} не найден");
        }

        return $repo->findWithDeleted($id) ?? (object) $rows[0];
    }

    /** @throws HttpException(422) если дата документа в закрытом периоде */
    private function checkClosedPeriod(string $docDate): void
    {
        $closed = SettingsService::closedPeriodDate();
        if ($closed !== null && substr($docDate, 0, 10) <= $closed) {
            throw new HttpException(422, "Дата документа ({$docDate}) попадает в закрытый период (до {$closed}). Проведение, распроведение и редактирование запрещены");
        }
    }

    /** При ошибке проведения пометить документ статусом 'error' (вне основной транзакции). */
    private function markError(DocumentRepository $repo, int $id, \Throwable $e): void
    {
        try {
            if (Database::inTransaction()) {
                Database::rollBack();
            }
            $repo->execute(
                'UPDATE ' . $repo->tableName() . " SET status = 'error', updated_at = NOW() WHERE id = ? AND status <> 'posted'",
                [$id]
            );
            AuditService::log($repo->tableName(), $id, 'post_error', null, ['message' => $e->getMessage()]);
        } catch (\Throwable) {
            // не мешаем исходному исключению
        }
    }

    /**
     * Сгруппировать строки ТЧ по номенклатуре: nomId => ['qty' => сумма, 'amount' => сумма].
     *
     * @param object[] $lines
     * @return array<int, array{qty: float, amount: float}>
     */
    private function groupByNomenclature(array $lines): array
    {
        $result = [];
        foreach ($lines as $line) {
            $nomId = (int) $line->nomenclature_id;
            $qty = (float) $line->quantity;
            $amount = (float) ($line->amount ?? ($qty * (float) $line->price));
            if ($qty <= 0) {
                throw new HttpException(422, "Строка {$line->line_no}: количество должно быть > 0");
            }
            $result[$nomId]['qty'] = ($result[$nomId]['qty'] ?? 0.0) + $qty;
            $result[$nomId]['amount'] = ($result[$nomId]['amount'] ?? 0.0) + $amount;
        }

        return $result;
    }

    private function purchasePrice(int $nomenclatureId): float
    {
        $row = $this->stock->stock()->queryOne(
            'SELECT purchase_price FROM catalogs_nomenclature WHERE id = ?',
            [$nomenclatureId]
        );

        return (float) ($row['purchase_price'] ?? 0);
    }
}
