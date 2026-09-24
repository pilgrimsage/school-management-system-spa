<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use App\Controllers\Data\AdminModulePages\AdminRoleManagementController;

/**
 * BaseController
 */
abstract class BaseController extends Controller
{
    /**
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    protected $helpers = [];

    protected $adminRoleManagementController;

    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger
    ) {
        parent::initController($request, $response, $logger);

        $this->adminRoleManagementController = new AdminRoleManagementController();

        /* -----------------------------------------
           Make the logged-in user available to every view (topbar
           profile name/email, etc.) without leaking password/token.
        ----------------------------------------- */
        if (isset($request->user)) {
            $record       = $request->user->record;
            $isEmployee   = $request->user->loginType === 'employee';
            $uploadFolder = $isEmployee ? 'employees' : 'students';

            service('renderer')->setVar('currentUser', [
                'type'          => $request->user->loginType,
                'id'            => $request->user->id,
                'name'          => trim(($record['firstname'] ?? '') . ' ' . ($record['lastname'] ?? '')),
                'email'         => $record['email1'] ?? ($record['student_email'] ?? ''),
                'profile_image' => !empty($record['profile_image'])
                    ? base_url('uploads/' . $uploadFolder . '/' . $record['profile_image'])
                    : base_url('assets/images/thumbs/user-img.png'),
            ]);
        }

        /* -----------------------------------------
           Inject role permissions for EMPLOYEES only
        ----------------------------------------- */
        if (isset($request->user)) {

            if ($request->user->loginType !== 'employee') {
                return;
            }

            // employee record already resolved by filter
            $employee = $request->user->record;

            if (!isset($employee['role_id'])) {
                return;
            }

            $roleToolPermissions = $this->getRoleToolPermissions(
                (int) $employee['role_id']
            );

            service('renderer')->setVar(
                'roleToolPermissions',
                $roleToolPermissions
            );
        }
    }

    /**
     * Get role tool permissions by role ID
     */
    protected function getRoleToolPermissions(int $roleId)
    {
        return $this->adminRoleManagementController->getOne($roleId);
    }

    /**
     * Verify a login password against a stored value that may be either a
     * password_hash() hash (current format) or plaintext (legacy rows
     * created before hashing was introduced). On a successful legacy
     * plaintext match, the stored value is transparently upgraded to a hash.
     */
    protected function verifyAndUpgradePassword(string $plainPassword, array $userRecord, $model): bool
    {
        $stored = $userRecord['password'] ?? '';

        if ($stored === '') {
            return false;
        }

        $info = password_get_info($stored);
        if ($info['algo'] !== null) {
            return password_verify($plainPassword, $stored);
        }

        // Legacy plaintext row — compare directly, then upgrade to a hash.
        if (!hash_equals($stored, $plainPassword)) {
            return false;
        }

        $model->update($userRecord['id'], [
            'password' => password_hash($plainPassword, PASSWORD_DEFAULT),
        ]);

        return true;
    }
}
