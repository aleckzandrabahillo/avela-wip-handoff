<section class="page-head"><div><h1>Orders</h1><p>Review orders and manage their fulfillment status.</p></div></section>
<?php if($error): ?><p class="alert alert-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="get" action="<?= e(url('/admin/orders')) ?>" class="staff-list-tools">
 <label class="field">Order or recipient<input type="search" name="q" maxlength="100" value="<?= e($history['q']) ?>"></label>
 <label class="field">Status<select name="status"><option value="">All statuses</option><?php foreach(['pending','processing','shipped','delivered','cancelled'] as $status): ?><option value="<?= e($status) ?>" <?= $history['status']===$status?'selected':'' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
 <button class="btn btn-primary" type="submit">Filter orders</button><a href="<?= e(url('/admin/orders')) ?>">Clear</a>
</form>
<p><?= (int)$history['total'] ?> matching orders</p>
<?php if(!$history['items']): ?><p class="empty-state">No orders match these filters.</p><?php else: ?>
<div class="order-table-wrap admin-order-table" tabindex="0" role="region" aria-label="Scrollable order list"><table><thead><tr><th scope="col">Order</th><th scope="col">Recipient</th><th scope="col">Placed</th><th scope="col" class="num">Total</th><th scope="col">Status</th></tr></thead><tbody>
<?php foreach($history['items'] as $item):
 $reference=\App\Services\OrderReference::display($item);
 $current=!empty($item['delivery_event_id'])&&$item['status']==='shipped'?'out_for_delivery':$item['status'];
 $next=$item['status']==='shipped'&&empty($item['delivery_event_id'])?'out_for_delivery':(\App\Services\OrderWorkflow::NEXT[$item['status']]??null);
 $canAdvance=($item['maya_state']??null)===null||$item['maya_state']==='paid';
 $choices=[];
 if($next&&$canAdvance)$choices[]=$next;
 if(in_array($item['status'],['pending','processing'],true)&&$item['payment_method']==='cod')$choices[]='cancelled';
 $statusId='order-status-'.(int)$item['id'];
?><tr>
 <td><a href="<?= e(url('/admin/orders/view?id='.(int)$item['id'])) ?>"><?= e($reference) ?></a></td><td><?= e($item['recipient_name_snapshot']??'Not recorded') ?></td><td><?= e($item['created_at']) ?></td><td class="num">₱<?= e(number_format((float)$item['total'],2)) ?></td>
 <td class="order-status-cell">
 <?php if($choices): ?>
 <form class="staff-order-status" method="post" action="<?= e(url('/admin/orders/status')) ?>" data-confirm="Save this order status? Cancelling an eligible cash-on-delivery order also restores its stock.">
  <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="expected_status" value="<?= e($item['status']) ?>"><input type="hidden" name="expected_delivery_event" value="<?= (int)($item['delivery_event_id']??0) ?>">
  <select id="<?= e($statusId) ?>" name="status" aria-label="<?= e('Status for '.$reference) ?>" required>
   <option value="" selected disabled><?= e(ucfirst(str_replace('_',' ',$current))) ?></option>
   <?php foreach($choices as $choice): ?><option value="<?= e($choice) ?>"><?= e(ucfirst(str_replace('_',' ',$choice))) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-secondary btn-sm" type="submit">Update<span class="sr-only"> status for <?= e($reference) ?></span></button>
 </form>
 <?php else: ?>
 <div class="staff-order-status"><select id="<?= e($statusId) ?>" aria-label="<?= e('Status for '.$reference) ?>" aria-describedby="<?= e($statusId) ?>-note" disabled><option><?= e(ucfirst(str_replace('_',' ',$current))) ?></option></select></div>
 <p class="muted small order-status-note" id="<?= e($statusId) ?>-note"><?= $next&&!$canAdvance?'Awaiting verified payment':'No further updates' ?></p>
 <?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div>
<?= pagination_nav((int)$history['page'],(int)$history['pages'],static fn(int $n):string=>url('/admin/orders?'.http_build_query(['q'=>$history['q'],'status'=>$history['status'],'page'=>$n])),'Administrator order pages') ?>
<?php endif; ?>
<details class="staff-help"><summary>About order updates and cancellations</summary><p>Pending or processing COD orders can be cancelled when their original stock deduction is confirmed. Shipped, delivered and older unreconciled orders require separate review.</p></details>
