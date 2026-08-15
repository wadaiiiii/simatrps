import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
export const curriculum = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: curriculum.url(options),
    method: 'get',
})

curriculum.definition = {
    methods: ["get","head"],
    url: '/admin/kurikulum',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
curriculum.url = (options?: RouteQueryOptions) => {
    return curriculum.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
curriculum.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: curriculum.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
curriculum.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: curriculum.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
    const curriculumForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: curriculum.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
        curriculumForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: curriculum.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
        curriculumForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: curriculum.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    curriculum.form = curriculumForm
/**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/template-rps'
 */
export const templates = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: templates.url(options),
    method: 'get',
})

templates.definition = {
    methods: ["get","head"],
    url: '/admin/template-rps',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/template-rps'
 */
templates.url = (options?: RouteQueryOptions) => {
    return templates.definition.url + queryParams(options)
}

/**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/template-rps'
 */
templates.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: templates.url(options),
    method: 'get',
})
/**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/template-rps'
 */
templates.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: templates.url(options),
    method: 'head',
})

    /**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/template-rps'
 */
    const templatesForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: templates.url(options),
        method: 'get',
    })

            /**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/template-rps'
 */
        templatesForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: templates.url(options),
            method: 'get',
        })
            /**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/template-rps'
 */
        templatesForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: templates.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    templates.form = templatesForm
/**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/pengguna'
 */
export const users = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: users.url(options),
    method: 'get',
})

users.definition = {
    methods: ["get","head"],
    url: '/admin/pengguna',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/pengguna'
 */
users.url = (options?: RouteQueryOptions) => {
    return users.definition.url + queryParams(options)
}

/**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/pengguna'
 */
users.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: users.url(options),
    method: 'get',
})
/**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/pengguna'
 */
users.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: users.url(options),
    method: 'head',
})

    /**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/pengguna'
 */
    const usersForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: users.url(options),
        method: 'get',
    })

            /**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/pengguna'
 */
        usersForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: users.url(options),
            method: 'get',
        })
            /**
* @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/admin/pengguna'
 */
        usersForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: users.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    users.form = usersForm
const admin = {
    curriculum: Object.assign(curriculum, curriculum),
templates: Object.assign(templates, templates),
users: Object.assign(users, users),
}

export default admin