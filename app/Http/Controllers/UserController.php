<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\SearchUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Services\SecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    protected UserService $userService;
    protected SecurityService $securityService;

    public function __construct(UserService $userService, SecurityService $securityService)
    {
        $this->userService = $userService;
        $this->securityService = $securityService;
    }

    /**
     * Lấy danh sách users
     */
    public function index(SearchUserRequest $request): JsonResponse
    {
        try {
            $perPage = $request->per_page;

            if ($request->keyword) {
                $users = $this->userService->searchUsers($request->keyword, $perPage);
            } else {
                $users = $this->userService->getAllUsers($perPage);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách người dùng thành công',
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('Error in UserController@index: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách người dùng',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Lấy thông tin chi tiết user
     */
    public function show(User $user): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin người dùng thành công',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Error in UserController@show: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin người dùng',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Tạo mới user
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            Log::info('Creating user with data: ', $request->validated());

            $user = $this->userService->createUser($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Tạo người dùng thành công',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error in UserController@store: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo người dùng',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin user
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            // Kiểm tra rate limit
            $rateLimitKey = "user_{$user->id}";
            if (!$this->securityService->checkRateLimit($rateLimitKey, 'profile_update', 5, 60)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã thay đổi thông tin quá nhiều lần. Vui lòng thử lại sau 1 giờ.'
                ], 429);
            }

            // Kiểm tra hành vi đáng ngờ
            $data = $request->validated();
            $suspiciousFields = [];

            foreach ($data as $field => $value) {
                if (in_array($field, ['birthday', 'email']) && $user->$field !== $value) {
                    if ($this->securityService->checkSuspiciousBehavior($user, $field, $user->$field, $value, $request)) {
                        $suspiciousFields[] = $field;
                    }
                }
            }

            // Nếu có hành vi đáng ngờ, ghi log và cảnh báo
            if (!empty($suspiciousFields)) {
                Log::warning("Phát hiện hành vi đáng ngờ khi cập nhật user {$user->id}", [
                    'suspicious_fields' => $suspiciousFields,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Vẫn cho phép cập nhật nhưng ghi log để theo dõi
                $data['is_suspicious'] = true;
            }

            Log::info('Updating user ' . $user->id . ' with data: ', $data);
            
            $updatedUser = $this->userService->updateUser($user->id, $data);
            
            if (!$updatedUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cập nhật thất bại'
                ], 400);
            }

            $response = [
                'success' => true,
                'message' => 'Cập nhật người dùng thành công',
                'data' => $updatedUser
            ];

            // Thêm cảnh báo nếu có hành vi đáng ngờ
            if (!empty($suspiciousFields)) {
                $response['warning'] = 'Hệ thống đã ghi nhận thay đổi của bạn. Nếu có hành vi bất thường, tài khoản có thể bị hạn chế.';
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error in UserController@update: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật người dùng',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Xóa user
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            $deleted = $this->userService->deleteUser($user->id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xóa thất bại'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Xóa người dùng thành công'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in UserController@destroy: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa người dùng',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
