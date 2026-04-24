<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\CustomerActivity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityFeedController extends Controller
{
    /**
     * @return list<string>
     */
    protected function activityListColumns(): array
    {
        return [
            'id',
            'customer_id',
            'user_id',
            'event_type',
            'category',
            'summary',
            'meta',
            'related_task_id',
            'created_at',
        ];
    }

    public function index(Request $request): View
    {
        return view('activity.index', $this->activityIndexPayload($request));
    }

    /**
     * @return array{title: string, activities: \Illuminate\Contracts\Pagination\LengthAwarePaginator, categoryLabels: array<string, string>}
     */
    protected function activityIndexPayload(Request $request): array
    {
        $customerSearch = trim((string) $request->query('customer', ''));

        $activities = CustomerActivity::query()
            ->select($this->activityListColumns())
            ->with(['user:id,name', 'customer:id,first_name,last_name,email', 'relatedTask:id,title'])
            ->when($customerSearch !== '', function ($query) use ($customerSearch) {
                $like = '%'.addcslashes($customerSearch, '%_\\').'%';
                $query->whereHas('customer', function ($q) use ($like) {
                    $q->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->timelineFilter($request)
            ->latest('created_at')
            ->paginate(35)
            ->withQueryString();

        return [
            'title' => 'Activity · BeautiSkin CRM',
            'activities' => $activities,
            'categoryLabels' => CustomerActivity::categoryLabels(),
        ];
    }

    public function customer(Request $request, Customer $customer): View
    {
        return view('customers.timeline', $this->customerTimelinePayload($request, $customer));
    }

    /**
     * @return array<string, mixed>
     */
    protected function customerTimelinePayload(Request $request, Customer $customer): array
    {
        $recentAppointments = Appointment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'booked')
            ->orderByDesc('scheduled_at')
            ->limit(20)
            ->get(['id', 'scheduled_at', 'status']);

        $activities = CustomerActivity::query()
            ->select($this->activityListColumns())
            ->where('customer_id', $customer->id)
            ->with(['user:id,name', 'relatedTask:id,title'])
            ->timelineFilter($request)
            ->latest('created_at')
            ->paginate(35)
            ->withQueryString();

        return [
            'title' => $customer->first_name.' '.$customer->last_name.' · Timeline',
            'customer' => $customer,
            'activities' => $activities,
            'categoryLabels' => CustomerActivity::categoryLabels(),
            'recentAppointments' => $recentAppointments,
        ];
    }
}
