<?php
session_start();
include '../config/db.php';
include '../config/auth.php';
include_once '../config/helpers.php';
requireLogin();
if (!hasPermission('view_purchase_orders')) { header("HTTP/1.0 403 Forbidden"); die('Access Denied'); }

$statusFilter = $_GET['status'] ?? '';
$where = $statusFilter ? "WHERE po.status = '" . $conn->real_escape_string($statusFilter) . "'" : '';
$orders = [];
$r = $conn->query("SELECT po.*, s.name as supplier_name, u.username as created_by,
    (SELECT SUM(quantity_ordered) FROM purchase_order_items WHERE purchase_order_id=po.id) as total_qty,
    (SELECT SUM(quantity_received) FROM purchase_order_items WHERE purchase_order_id=po.id) as received_qty
    FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id=s.id LEFT JOIN users u ON po.user_id=u.id $where ORDER BY po.order_date DESC LIMIT 200");
if($r) while($row=$r->fetch_assoc()) $orders[]=$row;

$suppliers=[];
$r2=$conn->query("SELECT id,name FROM suppliers WHERE status='active' ORDER BY name");
if($r2) while($row=$r2->fetch_assoc()) $suppliers[]=$row;

$products=[];
$r3=$conn->query("SELECT id,name,stock,cost_price FROM products WHERE status='active' ORDER BY name");
if($r3) while($row=$r3->fetch_assoc()) $products[]=$row;

include '../includes/header.php';
include '../includes/navbar.php';
?>
<div class="main-container">
<?php include '../includes/sidebar.php'; ?>
<div class="content-area"><div class="content-wrapper">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
<h1><i class="fas fa-file-invoice" style="margin-right:8px;"></i>Purchase Orders</h1>
<?php if(hasPermission('create_purchase_orders')): ?>
<button class="btn btn-primary" onclick="showCreatePO()"><i class="fas fa-plus"></i> New Purchase Order</button>
<?php endif; ?>
</div>

<div style="background:#f9fafb;padding:15px;border-radius:8px;margin:20px 0;border:1px solid #e5e7eb;">
<form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
<div style="min-width:160px;">
<label style="display:block;margin-bottom:5px;font-weight:600;color:#555;font-size:0.85rem;">Status</label>
<select name="status" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
<option value="">All</option>
<?php foreach(['draft','ordered','partially_received','received','cancelled'] as $st): ?>
<option value="<?=$st?>" <?=($statusFilter===$st)?'selected':''?>><?=ucwords(str_replace('_',' ',$st))?></option>
<?php endforeach; ?>
</select></div>
<div style="display:flex;gap:8px;">
<button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
<a href="purchase_orders.php" class="btn" style="padding:8px 15px;background:#e5e7eb;color:#333;text-decoration:none;border-radius:4px;"><i class="fas fa-redo"></i></a>
</div></form></div>

<table class="table">
<thead><tr><th>PO #</th><th>Supplier</th><th>Date</th><th>Items</th><th>Progress</th><th>Total Cost</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php if(empty($orders)): ?>
<tr><td colspan="8" style="text-align:center;padding:30px;color:#999;"><i class="fas fa-file-invoice" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.3;"></i>No purchase orders found.</td></tr>
<?php else: foreach($orders as $o):
$statusColors=['draft'=>['#f3f4f6','#374151'],'ordered'=>['#dbeafe','#1e40af'],'partially_received'=>['#fef3c7','#92400e'],'received'=>['#dcfce7','#166534'],'cancelled'=>['#fee2e2','#991b1b']];
$sc=$statusColors[$o['status']]??['#f3f4f6','#374151'];
$totalQty=intval($o['total_qty']);$recvQty=intval($o['received_qty']);
$pct=$totalQty>0?round($recvQty/$totalQty*100):0;
?>
<tr>
<td><strong>PO-<?=$o['id']?></strong></td>
<td><?=htmlspecialchars($o['supplier_name']??'No supplier')?></td>
<td style="font-size:0.85rem;white-space:nowrap;"><?=date('M d, Y',strtotime($o['order_date']))?></td>
<td><?=$recvQty?>/<?=$totalQty?></td>
<td><div style="background:#e5e7eb;border-radius:10px;height:8px;width:80px;display:inline-block;vertical-align:middle;"><div style="background:<?=$pct>=100?'#16a34a':'#f59e0b'?>;height:100%;border-radius:10px;width:<?=$pct?>%;"></div></div> <small><?=$pct?>%</small></td>
<td><strong>$<?=number_format($o['total_cost'],2)?></strong></td>
<td><span style="background:<?=$sc[0]?>;color:<?=$sc[1]?>;padding:3px 10px;border-radius:12px;font-size:0.78rem;font-weight:600;"><?=ucwords(str_replace('_',' ',$o['status']))?></span></td>
<td>
<button class="btn btn-info" onclick="viewPO(<?=$o['id']?>)" style="padding:5px 10px;" title="View"><i class="fas fa-eye"></i></button>
<?php if(in_array($o['status'],['draft','ordered','partially_received']) && hasPermission('receive_purchase_orders')): ?>
<button class="btn btn-primary" onclick="receivePO(<?=$o['id']?>)" style="padding:5px 10px;" title="Receive"><i class="fas fa-truck-loading"></i></button>
<?php endif; ?>
<?php if($o['status']==='draft' && hasPermission('create_purchase_orders')): ?>
<button class="btn btn-warning" onclick="updatePOStatus(<?=$o['id']?>,'ordered')" style="padding:5px 10px;background:#f59e0b;color:white;border:none;border-radius:4px;cursor:pointer;" title="Mark Ordered"><i class="fas fa-paper-plane"></i></button>
<?php endif; ?>
</td></tr>
<?php endforeach; endif; ?>
</tbody></table>
</div></div></div>

<!-- Create PO Modal -->
<div id="createPOModal" class="modal"><div class="modal-content" style="max-width:700px;">
<span class="close" onclick="closeCreatePO()">&times;</span>
<h2><i class="fas fa-file-invoice" style="margin-right:8px;color:#6F4E37;"></i>New Purchase Order</h2>
<form id="createPOForm">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
<div class="form-group"><label>Supplier</label>
<select id="poSupplier"><option value="">No supplier</option>
<?php foreach($suppliers as $s): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['name'])?></option><?php endforeach; ?>
</select></div>
<div class="form-group"><label>Expected Date</label><input type="date" id="poExpectedDate"></div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
<div class="form-group"><label>Reference #</label><input type="text" id="poReference" placeholder="External invoice/ref number"></div>
<div class="form-group"><label>Status</label><select id="poStatus"><option value="draft">Draft</option><option value="ordered">Ordered</option></select></div>
</div>
<div class="form-group"><label>Notes</label><textarea id="poNotes" rows="2" placeholder="Optional notes..."></textarea></div>

<h3 style="margin:20px 0 10px;color:#6F4E37;border-top:1px solid #e5e7eb;padding-top:15px;"><i class="fas fa-list"></i> Order Items</h3>
<div id="poItemsContainer"></div>
<button type="button" class="btn" onclick="addPOItem()" style="margin:10px 0;background:#e5e7eb;color:#333;padding:8px 15px;border:none;border-radius:6px;cursor:pointer;"><i class="fas fa-plus"></i> Add Item</button>

<div id="poTotalDisplay" style="text-align:right;font-size:1.1rem;font-weight:700;color:#6F4E37;margin:15px 0;">Total: $0.00</div>
<div style="display:flex;gap:10px;margin-top:15px;">
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Order</button>
<button type="button" class="btn btn-secondary" onclick="closeCreatePO()">Cancel</button>
</div></form></div></div>

<!-- View PO Modal -->
<div id="viewPOModal" class="modal"><div class="modal-content" style="max-width:650px;">
<span class="close" onclick="document.getElementById('viewPOModal').style.display='none'">&times;</span>
<h2 id="viewPOTitle"></h2>
<div id="viewPOContent" style="margin-top:15px;"></div>
</div></div>

<!-- Receive PO Modal -->
<div id="receivePOModal" class="modal"><div class="modal-content" style="max-width:650px;">
<span class="close" onclick="document.getElementById('receivePOModal').style.display='none'">&times;</span>
<h2 id="receivePOTitle"></h2>
<form id="receivePOForm">
<input type="hidden" id="receivePOId">
<div id="receiveItemsContainer" style="margin:15px 0;"></div>
<div style="display:flex;gap:10px;margin-top:20px;">
<button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Confirm Receive</button>
<button type="button" class="btn btn-secondary" onclick="document.getElementById('receivePOModal').style.display='none'">Cancel</button>
</div></form></div></div>

<style>
.modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5);}
.modal-content{background:#fff;margin:4% auto;padding:30px;border-radius:12px;width:92%;max-height:88vh;overflow-y:auto;box-shadow:0 8px 30px rgba(0,0,0,0.2);}
.close{color:#aaa;float:right;font-size:28px;cursor:pointer;}.close:hover{color:#000;}
.form-group{margin-bottom:15px;}.form-group label{display:block;margin-bottom:5px;font-weight:600;color:#555;}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px;border:2px solid #E8D9C8;border-radius:8px;font-size:0.95rem;font-family:inherit;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#8B6F47;}
.btn-secondary{background:#888;color:#fff;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;}.btn-secondary:hover{background:#666;}
.po-item-row{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;align-items:end;padding:10px;background:#f9fafb;border-radius:8px;margin-bottom:8px;}
.po-item-row select,.po-item-row input{padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:0.9rem;}
.po-item-row label{font-size:0.78rem;font-weight:600;color:#888;margin-bottom:3px;display:block;}
.recv-item-row{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:10px;align-items:center;padding:12px;background:#f9fafb;border-radius:8px;margin-bottom:8px;}
@media(max-width:768px){.po-item-row,.recv-item-row{grid-template-columns:1fr;}}
</style>

<script>
const productsData=<?=json_encode($products)?>;
let poItemIndex=0;

function showCreatePO(){document.getElementById('createPOForm').reset();document.getElementById('poItemsContainer').innerHTML='';poItemIndex=0;addPOItem();document.getElementById('createPOModal').style.display='block';}
function closeCreatePO(){document.getElementById('createPOModal').style.display='none';}

function addPOItem(){
const i=poItemIndex++;
const opts=productsData.map(p=>`<option value="${p.id}" data-cost="${p.cost_price}">${p.name} (Stock: ${p.stock})</option>`).join('');
document.getElementById('poItemsContainer').insertAdjacentHTML('beforeend',
`<div class="po-item-row" id="poItem${i}">
<div><label>Product</label><select onchange="updatePOItemCost(${i})" id="poItemProd${i}"><option value="">Select...</option>${opts}</select></div>
<div><label>Qty</label><input type="number" id="poItemQty${i}" min="1" value="1" oninput="updatePOTotal()"></div>
<div><label>Unit Cost</label><input type="number" id="poItemCost${i}" step="0.01" min="0" value="0" oninput="updatePOTotal()"></div>
<div><label>Subtotal</label><span id="poItemSub${i}" style="font-weight:700;color:#6F4E37;">$0.00</span></div>
<div><button type="button" onclick="document.getElementById('poItem${i}').remove();updatePOTotal();" style="background:#fee2e2;color:#991b1b;border:none;padding:8px 10px;border-radius:6px;cursor:pointer;margin-top:18px;"><i class="fas fa-times"></i></button></div>
</div>`);
}

function updatePOItemCost(i){
const sel=document.getElementById('poItemProd'+i);
const opt=sel?.options[sel.selectedIndex];
if(opt?.dataset.cost) document.getElementById('poItemCost'+i).value=parseFloat(opt.dataset.cost);
updatePOTotal();
}

function updatePOTotal(){
let total=0;
document.querySelectorAll('.po-item-row').forEach(row=>{
const id=row.id.replace('poItem','');
const q=parseFloat(document.getElementById('poItemQty'+id)?.value||0);
const c=parseFloat(document.getElementById('poItemCost'+id)?.value||0);
const sub=q*c;total+=sub;
const subEl=document.getElementById('poItemSub'+id);
if(subEl) subEl.textContent='$'+sub.toFixed(2);
});
document.getElementById('poTotalDisplay').textContent='Total: $'+total.toFixed(2);
}

document.getElementById('createPOForm').addEventListener('submit',async e=>{
e.preventDefault();
const items=[];
document.querySelectorAll('.po-item-row').forEach(row=>{
const id=row.id.replace('poItem','');
const pid=document.getElementById('poItemProd'+id)?.value;
const qty=parseInt(document.getElementById('poItemQty'+id)?.value||0);
const cost=parseFloat(document.getElementById('poItemCost'+id)?.value||0);
if(pid&&qty>0) items.push({product_id:parseInt(pid),quantity_ordered:qty,unit_cost:cost});
});
if(!items.length){alert('Add at least one item');return;}
const data={action:'create',supplier_id:parseInt(document.getElementById('poSupplier').value)||null,expected_date:document.getElementById('poExpectedDate').value,reference_number:document.getElementById('poReference').value.trim(),status:document.getElementById('poStatus').value,notes:document.getElementById('poNotes').value.trim(),items};
try{
const res=await fetch('../api/purchase_orders.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
const r=await res.json();
if(r.status==='success'){typeof vendixNotifyAndReload==='function'?vendixNotifyAndReload(r.message,'success'):(alert(r.message),location.reload());}
else alert('Error: '+r.message);
}catch(err){alert('Error: '+err.message);}
});

async function viewPO(id){
try{
const res=await fetch('../api/purchase_orders.php?id='+id);
const r=await res.json();
if(r.status!=='success'){alert(r.message);return;}
const po=r.data,items=r.items;
document.getElementById('viewPOTitle').innerHTML=`<i class="fas fa-file-invoice" style="margin-right:8px;color:#6F4E37;"></i>PO-${po.id} Details`;
let html=`<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;">
<div><strong>Supplier:</strong> ${po.supplier_name||'N/A'}</div>
<div><strong>Status:</strong> ${po.status.replace('_',' ')}</div>
<div><strong>Order Date:</strong> ${po.order_date}</div>
<div><strong>Expected:</strong> ${po.expected_date||'N/A'}</div>
<div><strong>Reference:</strong> ${po.reference_number||'N/A'}</div>
<div><strong>Created By:</strong> ${po.created_by||'N/A'}</div>
</div>`;
if(po.notes) html+=`<div style="background:#fef3c7;padding:10px;border-radius:6px;margin-bottom:15px;"><strong>Notes:</strong> ${po.notes}</div>`;
html+=`<table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
<thead><tr style="background:#f3f4f6;"><th style="padding:10px;text-align:left;">Product</th><th style="padding:10px;">Ordered</th><th style="padding:10px;">Received</th><th style="padding:10px;">Unit Cost</th><th style="padding:10px;">Subtotal</th></tr></thead><tbody>`;
let total=0;
items.forEach(it=>{
total+=parseFloat(it.subtotal);
const full=parseInt(it.quantity_received)>=parseInt(it.quantity_ordered);
html+=`<tr style="border-bottom:1px solid #e5e7eb;">
<td style="padding:10px;">${it.product_name}</td>
<td style="padding:10px;text-align:center;">${it.quantity_ordered}</td>
<td style="padding:10px;text-align:center;color:${full?'#16a34a':'#f59e0b'};font-weight:700;">${it.quantity_received}/${it.quantity_ordered}</td>
<td style="padding:10px;text-align:center;">$${parseFloat(it.unit_cost).toFixed(2)}</td>
<td style="padding:10px;text-align:center;font-weight:600;">$${parseFloat(it.subtotal).toFixed(2)}</td></tr>`;
});
html+=`</tbody><tfoot><tr style="background:#f9fafb;font-weight:700;"><td colspan="4" style="padding:10px;text-align:right;">Total:</td><td style="padding:10px;text-align:center;color:#6F4E37;">$${total.toFixed(2)}</td></tr></tfoot></table>`;
document.getElementById('viewPOContent').innerHTML=html;
document.getElementById('viewPOModal').style.display='block';
}catch(err){alert('Error: '+err.message);}
}

async function receivePO(id){
try{
const res=await fetch('../api/purchase_orders.php?id='+id);
const r=await res.json();
if(r.status!=='success'){alert(r.message);return;}
const po=r.data,items=r.items;
document.getElementById('receivePOId').value=id;
document.getElementById('receivePOTitle').innerHTML=`<i class="fas fa-truck-loading" style="margin-right:8px;color:#6F4E37;"></i>Receive Items — PO-${id}`;
let html='';
items.forEach(it=>{
const remaining=parseInt(it.quantity_ordered)-parseInt(it.quantity_received);
if(remaining<=0) return;
html+=`<div class="recv-item-row">
<div><strong>${it.product_name}</strong><br><small style="color:#888;">Ordered: ${it.quantity_ordered} | Received: ${it.quantity_received} | Remaining: ${remaining}</small></div>
<div><label style="font-size:0.78rem;font-weight:600;color:#888;">Receiving</label><input type="number" class="recv-qty" data-poi="${it.id}" data-max="${remaining}" min="0" max="${remaining}" value="${remaining}" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;width:100%;"></div>
<div style="text-align:center;"><span style="background:#dbeafe;color:#1e40af;padding:3px 8px;border-radius:10px;font-size:0.8rem;">Max: ${remaining}</span></div>
<div style="text-align:center;font-size:0.85rem;color:#888;">Stock: ${it.current_stock}</div>
</div>`;
});
if(!html) html='<p style="text-align:center;color:#16a34a;padding:20px;"><i class="fas fa-check-circle"></i> All items have been fully received.</p>';
document.getElementById('receiveItemsContainer').innerHTML=html;
document.getElementById('receivePOModal').style.display='block';
}catch(err){alert('Error: '+err.message);}
}

document.getElementById('receivePOForm').addEventListener('submit',async e=>{
e.preventDefault();
const poId=parseInt(document.getElementById('receivePOId').value);
const items=[];
document.querySelectorAll('.recv-qty').forEach(inp=>{
const qty=parseInt(inp.value||0);
if(qty>0) items.push({poi_id:parseInt(inp.dataset.poi),quantity_receiving:qty});
});
if(!items.length){alert('Enter quantities to receive');return;}
try{
const res=await fetch('../api/purchase_orders.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'receive',po_id:poId,items})});
const r=await res.json();
if(r.status==='success'){typeof vendixNotifyAndReload==='function'?vendixNotifyAndReload(r.message,'success'):(alert(r.message),location.reload());}
else alert('Error: '+r.message);
}catch(err){alert('Error: '+err.message);}
});

async function updatePOStatus(id,status){
const msg=status==='ordered'?'Mark this PO as ordered?':'Change status to '+status+'?';
const confirmed=typeof vendixConfirm==='function'?await vendixConfirm(msg,{title:'Update Status',acceptLabel:'Confirm'}):confirm(msg);
if(!confirmed) return;
try{
const res=await fetch('../api/purchase_orders.php',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,status})});
const r=await res.json();
if(r.status==='success'){typeof vendixNotifyAndReload==='function'?vendixNotifyAndReload(r.message,'success'):(alert(r.message),location.reload());}
else alert('Error: '+r.message);
}catch(err){alert('Error: '+err.message);}
}

document.querySelectorAll('.modal').forEach(m=>{m.addEventListener('click',e=>{if(e.target===m)m.style.display='none';});});
</script>
