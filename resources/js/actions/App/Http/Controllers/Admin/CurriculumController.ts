import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
const CurriculumController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CurriculumController.url(options),
    method: 'get',
})

CurriculumController.definition = {
    methods: ["get","head"],
    url: '/admin/kurikulum',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
CurriculumController.url = (options?: RouteQueryOptions) => {
    return CurriculumController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
CurriculumController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CurriculumController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
CurriculumController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CurriculumController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
    const CurriculumControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: CurriculumController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
        CurriculumControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: CurriculumController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\CurriculumController::__invoke
 * @see app/Http/Controllers/Admin/CurriculumController.php:13
 * @route '/admin/kurikulum'
 */
        CurriculumControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: CurriculumController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    CurriculumController.form = CurriculumControllerForm
export default CurriculumController