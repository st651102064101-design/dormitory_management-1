SELECT e.exp_id, e.exp_month, e.exp_total, (SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')) as paid_sum FROM expense e WHERE e.ctr_id = 51
